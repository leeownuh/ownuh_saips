from __future__ import annotations

from collections import defaultdict
from datetime import datetime
from typing import Any, Dict, Iterable, List, Tuple


def _parse_stamp(value: str) -> datetime | None:
    text = str(value).strip()
    if text == "":
        return None
    for fmt in ("%Y-%m-%d %H:%M:%S.%f", "%Y-%m-%d %H:%M:%S"):
        try:
            return datetime.strptime(text, fmt)
        except ValueError:
            continue
    try:
        parsed = datetime.fromisoformat(text.replace("Z", "+00:00"))
        return parsed.replace(tzinfo=None)
    except ValueError:
        return None


class TemporalGraphAttributionModel:
    """
    Lightweight dynamic graph scorer for attribution:
    - Builds heterogeneous node relations per event
    - Applies exponential time decay on edge contributions
    - Returns normalized suspiciousness for case-level evaluation
    """

    def __init__(self, half_life_hours: float = 24.0):
        self.half_life_hours = max(0.5, float(half_life_hours))

    def score_case(self, events: List[Dict[str, Any]], expected_entities: Iterable[str] | None = None) -> float:
        if not events:
            return 0.0

        scored_edges = self._build_decayed_edges(events)
        if not scored_edges:
            return 0.0

        node_strength = self._node_strength(scored_edges)
        if not node_strength:
            return 0.0

        max_strength = max(node_strength.values())
        if max_strength <= 1e-8:
            return 0.0

        target_strength = 0.0
        target_entities = list(expected_entities or [])
        for entity in target_entities:
            target_strength = max(target_strength, node_strength.get(str(entity), 0.0))

        if target_strength <= 0 and target_entities:
            # Fallback: match by suffix in case expected entity formatting differs.
            for entity in target_entities:
                suffix = str(entity).split(":", 1)[-1]
                for node_id, strength in node_strength.items():
                    if node_id.endswith(suffix):
                        target_strength = max(target_strength, strength)

        if target_strength <= 0:
            target_strength = max_strength

        temporal_volatility = self._temporal_volatility(events)
        normalized = target_strength / max_strength
        score = (normalized * 0.75) + (temporal_volatility * 0.25)
        return max(0.0, min(1.0, float(score)))

    def _build_decayed_edges(self, events: List[Dict[str, Any]]) -> Dict[Tuple[str, str], float]:
        stamped_events = []
        for event in events:
            stamp = _parse_stamp(str(event.get("created_at", "")))
            if stamp is None:
                continue
            stamped_events.append((stamp, event))
        if not stamped_events:
            return {}

        latest = max(stamp for stamp, _ in stamped_events)
        edges: Dict[Tuple[str, str], float] = defaultdict(float)
        for stamp, event in stamped_events:
            recency = self._recency_weight((latest - stamp).total_seconds() / 3600.0)
            nodes = self._event_nodes(event)
            if len(nodes) < 2:
                continue
            for left_index in range(len(nodes)):
                for right_index in range(left_index + 1, len(nodes)):
                    edge = tuple(sorted((nodes[left_index], nodes[right_index])))
                    edges[edge] += recency
        return dict(edges)

    def _event_nodes(self, event: Dict[str, Any]) -> List[str]:
        nodes: List[str] = []
        user_id = str(event.get("user_id", "")).strip()
        source_ip = str(event.get("source_ip", "")).strip()
        device = str(event.get("device_fingerprint", "")).strip()
        country = str(event.get("country_code", "")).strip()
        session_id = str((event.get("details") or {}).get("session_id", "")).strip() if isinstance(event.get("details"), dict) else ""

        if user_id and user_id.lower() != "unknown":
            nodes.append(f"user:{user_id}")
        if source_ip:
            nodes.append(f"ip:{source_ip}")
        if device:
            nodes.append(f"device:{device}")
        if country:
            nodes.append(f"country:{country}")
        if session_id:
            nodes.append(f"session:{session_id}")
        return nodes

    def _node_strength(self, edges: Dict[Tuple[str, str], float]) -> Dict[str, float]:
        strength: Dict[str, float] = defaultdict(float)
        for (left, right), weight in edges.items():
            strength[left] += weight
            strength[right] += weight
        return dict(strength)

    def _recency_weight(self, age_hours: float) -> float:
        # Half-life decay so recent interactions matter more.
        return 2.0 ** (-(max(0.0, age_hours) / self.half_life_hours))

    def _temporal_volatility(self, events: List[Dict[str, Any]]) -> float:
        user_countries: Dict[str, List[Tuple[datetime, str]]] = defaultdict(list)
        for event in events:
            user_id = str(event.get("user_id", "")).strip()
            if user_id == "" or user_id.lower() == "unknown":
                continue
            stamp = _parse_stamp(str(event.get("created_at", "")))
            country = str(event.get("country_code", "")).strip()
            if stamp is None or country == "":
                continue
            user_countries[user_id].append((stamp, country))

        switches = 0
        comparisons = 0
        for pairs in user_countries.values():
            ordered = sorted(pairs, key=lambda item: item[0])
            for index in range(1, len(ordered)):
                comparisons += 1
                if ordered[index - 1][1] != ordered[index][1]:
                    switches += 1

        if comparisons == 0:
            return 0.0
        return min(1.0, switches / comparisons)
