from __future__ import annotations

from collections import Counter, defaultdict
from datetime import datetime
from typing import Any, Dict, Iterable, List, Tuple

import numpy as np


LOGIN_SUCCESS_CODES = {"AUTH-001"}
LOGIN_FAILURE_CODES = {"AUTH-002"}
MFA_BYPASS_CODES = {"AUTH-017", "AUTH-018"}


def event_code(event: Dict[str, Any]) -> str:
    return str(event.get("event_code", "")).upper()


def event_name(event: Dict[str, Any]) -> str:
    return str(event.get("event_name", "")).lower()


def event_details(event: Dict[str, Any]) -> Dict[str, Any]:
    details = event.get("details") or {}
    return details if isinstance(details, dict) else {}


def parse_event_timestamp(event: Dict[str, Any]) -> datetime | None:
    raw = event.get("created_at") or event_details(event).get("timestamp")
    if not raw:
        return None

    text = str(raw).strip()
    if text == "":
        return None

    candidates = [
        text,
        text.replace("Z", "+00:00"),
    ]
    for candidate in candidates:
        try:
            parsed = datetime.fromisoformat(candidate)
            return parsed.replace(tzinfo=None) if parsed.tzinfo is not None else parsed
        except ValueError:
            continue

    for fmt in ("%Y-%m-%d %H:%M:%S.%f", "%Y-%m-%d %H:%M:%S"):
        try:
            return datetime.strptime(text, fmt)
        except ValueError:
            continue

    return None


def is_login_success(event: Dict[str, Any]) -> bool:
    code = event_code(event)
    name = event_name(event)
    return code in LOGIN_SUCCESS_CODES or "successful login" in name


def is_login_failure(event: Dict[str, Any]) -> bool:
    code = event_code(event)
    name = event_name(event)
    return code in LOGIN_FAILURE_CODES or "failed login" in name or "login failed" in name


def is_login_event(event: Dict[str, Any]) -> bool:
    return is_login_success(event) or is_login_failure(event)


def is_mfa_bypass(event: Dict[str, Any]) -> bool:
    code = event_code(event)
    name = event_name(event)
    mfa_method = str(event.get("mfa_method", "")).lower()
    return code in MFA_BYPASS_CODES or "bypass" in name or mfa_method == "bypass_token"


def is_mfa_event(event: Dict[str, Any]) -> bool:
    code = event_code(event)
    name = event_name(event)
    mfa_method = str(event.get("mfa_method", "")).lower()
    return code == "AUTH-000" or "mfa" in name or "otp" in name or mfa_method not in {"", "none"}


def is_mfa_failure(event: Dict[str, Any]) -> bool:
    name = event_name(event)
    details_blob = jsonish_lower(event_details(event))
    return (
        ("mfa" in name or "otp" in name or "mfa" in details_blob or "otp" in details_blob)
        and ("fail" in name or "denied" in name or "retry" in details_blob or "fail" in details_blob)
    )


def is_role_change(event: Dict[str, Any]) -> bool:
    code = event_code(event)
    name = event_name(event)
    details_blob = jsonish_lower(event_details(event))
    return code.startswith("RBAC-") or "role changed" in name or "privilege" in name or "role" in details_blob


def is_admin_action(event: Dict[str, Any]) -> bool:
    code = event_code(event)
    name = event_name(event)
    user_agent = str(event.get("user_agent", "")).lower()
    role = str(event.get("role", "")).lower()
    return is_role_change(event) or code.startswith("ADMIN-") or "suspended" in name or "admin console" in user_agent or role in {"admin", "superadmin"}


def jsonish_lower(value: Dict[str, Any]) -> str:
    return str(value).lower()


def group_events_by_user(events: List[Dict[str, Any]]) -> Dict[str, List[Dict[str, Any]]]:
    grouped: Dict[str, List[Dict[str, Any]]] = defaultdict(list)
    for event in events:
        user_id = str(event.get("user_id", "")).strip()
        if user_id == "" or user_id.lower() == "unknown":
            continue
        grouped[user_id].append(event)
    return dict(grouped)


def user_behavior_feature_names() -> List[str]:
    return [
        "event_count",
        "failed_login_attempts",
        "successful_logins",
        "failed_login_velocity_per_hour",
        "failed_login_velocity_10m",
        "geographic_diversity",
        "ip_diversity",
        "device_diversity",
        "avg_login_interval_hours",
        "min_login_interval_minutes",
        "ip_burstiness",
        "burst_5min_peak",
        "country_switches",
        "device_novelty_ratio",
        "off_hours_access_ratio",
        "recent_30m_failure_ratio",
        "consecutive_failure_streak",
        "risk_trend_slope",
        "mfa_event_count",
        "mfa_failure_rate",
        "mfa_bypass_attempts",
        "avg_risk_score",
        "max_risk_score",
        "session_count",
        "session_reuse_ratio",
        "admin_action_count",
        "admin_action_proximity",
        "success_after_failure_ratio",
        "unique_user_agents",
    ]


def build_user_behavioral_summaries(events: List[Dict[str, Any]]) -> Dict[str, Dict[str, Any]]:
    summaries: Dict[str, Dict[str, Any]] = {}
    for user_id, user_events in group_events_by_user(events).items():
        summaries[user_id] = summarize_user_events(user_id, user_events)
    return summaries


def summarize_user_events(user_id: str, user_events: List[Dict[str, Any]]) -> Dict[str, Any]:
    ordered_events = sorted(
        user_events,
        key=lambda event: parse_event_timestamp(event) or datetime.min,
    )
    login_events = [event for event in ordered_events if is_login_event(event)]
    login_timestamps = [stamp for stamp in (parse_event_timestamp(event) for event in login_events) if stamp is not None]

    duration_hours = max(_duration_hours(login_timestamps), 0.25)
    intervals_hours = [
        (later - earlier).total_seconds() / 3600.0
        for earlier, later in pairwise(login_timestamps)
        if later >= earlier
    ]
    min_interval_minutes = min((value * 60.0 for value in intervals_hours), default=0.0)

    failed_count = sum(1 for event in ordered_events if is_login_failure(event))
    success_count = sum(1 for event in ordered_events if is_login_success(event))
    failed_velocity_10m = _failed_login_velocity_window(ordered_events, window_minutes=10)

    ip_values = [str(event.get("source_ip", "")).strip() for event in login_events if str(event.get("source_ip", "")).strip() != ""]
    device_values = [str(event.get("device_fingerprint", "")).strip() for event in login_events if str(event.get("device_fingerprint", "")).strip() != ""]
    country_values = [str(event.get("country_code", "")).strip() for event in login_events if str(event.get("country_code", "")).strip() != ""]
    user_agent_values = [str(event.get("user_agent", "")).strip() for event in login_events if str(event.get("user_agent", "")).strip() != ""]

    ip_counter = Counter(ip_values)
    device_counter = Counter(device_values)

    ip_burstiness = _ip_burstiness(login_events)
    burst_5min_peak = _burst_peak_count(login_events, window_minutes=5)
    device_novelty_ratio = (sum(1 for count in device_counter.values() if count == 1) / max(1, len(device_counter))) if device_counter else 0.0
    country_switches = sum(
        1
        for previous, current in pairwise(country_values)
        if previous != "" and current != "" and previous != current
    )

    off_hours_ratio = (
        sum(1 for stamp in login_timestamps if stamp.hour < 6 or stamp.hour >= 22) / max(1, len(login_timestamps))
        if login_timestamps
        else 0.0
    )

    mfa_events = [event for event in ordered_events if is_mfa_event(event)]
    mfa_failure_count = sum(1 for event in ordered_events if is_mfa_failure(event))
    mfa_bypass_count = sum(1 for event in ordered_events if is_mfa_bypass(event))

    risk_scores = [float(event.get("risk_score", 0) or 0.0) for event in ordered_events]
    risk_slope = _risk_trend_slope(ordered_events)
    session_ids = [
        str(event_details(event).get("session_id", "")).strip()
        for event in ordered_events
        if str(event_details(event).get("session_id", "")).strip() != ""
    ]
    session_counter = Counter(session_ids)
    repeated_session_hits = sum(max(0, count - 1) for count in session_counter.values())

    admin_events = [event for event in ordered_events if is_admin_action(event)]
    suspicious_stamps = [
        stamp
        for event in ordered_events
        for stamp in [parse_event_timestamp(event)]
        if stamp is not None and (is_login_failure(event) or float(event.get("risk_score", 0) or 0.0) >= 70.0)
    ]
    admin_action_proximity = 0
    for event in admin_events:
        stamp = parse_event_timestamp(event)
        if stamp is None:
            continue
        if any(abs((stamp - suspicious_stamp).total_seconds()) <= 1800 for suspicious_stamp in suspicious_stamps):
            admin_action_proximity += 1

    success_after_failure_ratio = _success_after_failure_ratio(ordered_events)
    recent_30m_failure_ratio = _recent_window_failure_ratio(ordered_events, window_minutes=30)
    consecutive_failure_streak = _consecutive_failure_streak(ordered_events)

    summary = {
        "user_id": user_id,
        "event_count": len(ordered_events),
        "failed_login_attempts": failed_count,
        "successful_logins": success_count,
        "failed_login_velocity_per_hour": failed_count / duration_hours,
        "failed_login_velocity_10m": failed_velocity_10m,
        "geographic_diversity": len(set(country_values)),
        "ip_diversity": len(set(ip_values)),
        "device_diversity": len(set(device_values)),
        "avg_login_interval_hours": float(np.mean(intervals_hours)) if intervals_hours else 0.0,
        "min_login_interval_minutes": min_interval_minutes,
        "ip_burstiness": ip_burstiness,
        "burst_5min_peak": burst_5min_peak,
        "country_switches": float(country_switches),
        "device_novelty_ratio": device_novelty_ratio,
        "off_hours_access_ratio": off_hours_ratio,
        "recent_30m_failure_ratio": recent_30m_failure_ratio,
        "consecutive_failure_streak": consecutive_failure_streak,
        "risk_trend_slope": risk_slope,
        "mfa_event_count": len(mfa_events),
        "mfa_failure_rate": mfa_failure_count / max(1, len(mfa_events)),
        "mfa_bypass_attempts": mfa_bypass_count,
        "avg_risk_score": float(np.mean(risk_scores)) if risk_scores else 0.0,
        "max_risk_score": max(risk_scores, default=0.0),
        "session_count": len(session_counter),
        "session_reuse_ratio": repeated_session_hits / max(1, len(session_ids)),
        "admin_action_count": len(admin_events),
        "admin_action_proximity": admin_action_proximity / max(1, len(admin_events)) if admin_events else 0.0,
        "success_after_failure_ratio": success_after_failure_ratio,
        "unique_user_agents": len(set(user_agent_values)),
    }

    summary["feature_vector"] = [float(summary[name]) for name in user_behavior_feature_names()]
    return summary


def summaries_to_matrix(summaries: Dict[str, Dict[str, Any]]) -> Tuple[np.ndarray, List[str], List[Dict[str, Any]]]:
    ids = list(summaries.keys())
    ordered = [summaries[user_id] for user_id in ids]
    if not ordered:
        return np.array([]).reshape(0, len(user_behavior_feature_names())), [], []
    matrix = np.array([summary["feature_vector"] for summary in ordered], dtype=float)
    return matrix, ids, ordered


def pairwise(items: Iterable[datetime]) -> Iterable[Tuple[datetime, datetime]]:
    sequence = list(items)
    for index in range(1, len(sequence)):
        yield sequence[index - 1], sequence[index]


def _duration_hours(stamps: List[datetime]) -> float:
    if len(stamps) < 2:
        return 0.25
    return max((stamps[-1] - stamps[0]).total_seconds() / 3600.0, 0.25)


def _ip_burstiness(events: List[Dict[str, Any]]) -> float:
    if not events:
        return 0.0

    counts: Dict[Tuple[str, str], int] = defaultdict(int)
    for event in events:
        ip = str(event.get("source_ip", "")).strip()
        stamp = parse_event_timestamp(event)
        if ip == "" or stamp is None:
            continue
        bucket = stamp.strftime("%Y-%m-%d %H:%M")
        quarter_hour = bucket[:-1] + str((stamp.minute // 15) * 15 // 10)
        counts[(ip, quarter_hour)] += 1

    if not counts:
        return 0.0

    return max(counts.values()) / max(1, len(events))


def _success_after_failure_ratio(events: List[Dict[str, Any]]) -> float:
    failure_stamps = [
        parse_event_timestamp(event)
        for event in events
        if is_login_failure(event)
    ]
    failure_stamps = [stamp for stamp in failure_stamps if stamp is not None]
    success_stamps = [
        parse_event_timestamp(event)
        for event in events
        if is_login_success(event)
    ]
    success_stamps = [stamp for stamp in success_stamps if stamp is not None]

    if not success_stamps:
        return 0.0

    success_after_failure = 0
    for success_stamp in success_stamps:
        if any(0 <= (success_stamp - failure_stamp).total_seconds() <= 1800 for failure_stamp in failure_stamps):
            success_after_failure += 1

    return success_after_failure / max(1, len(success_stamps))


def _failed_login_velocity_window(events: List[Dict[str, Any]], window_minutes: int) -> float:
    if window_minutes <= 0:
        return 0.0
    failed_stamps = [
        parse_event_timestamp(event)
        for event in events
        if is_login_failure(event)
    ]
    failed_stamps = [stamp for stamp in failed_stamps if stamp is not None]
    if not failed_stamps:
        return 0.0
    peak = _peak_events_in_window(failed_stamps, window_minutes)
    return float(peak) / float(window_minutes)


def _recent_window_failure_ratio(events: List[Dict[str, Any]], window_minutes: int) -> float:
    stamps = [
        (event, parse_event_timestamp(event))
        for event in events
    ]
    stamps = [(event, stamp) for event, stamp in stamps if stamp is not None]
    if not stamps:
        return 0.0
    latest_stamp = max(stamp for _, stamp in stamps)
    window_seconds = float(window_minutes * 60)
    recent_events = [
        event
        for event, stamp in stamps
        if 0 <= (latest_stamp - stamp).total_seconds() <= window_seconds
    ]
    if not recent_events:
        return 0.0
    recent_failures = sum(1 for event in recent_events if is_login_failure(event))
    return recent_failures / max(1, len(recent_events))


def _consecutive_failure_streak(events: List[Dict[str, Any]]) -> float:
    longest = 0
    current = 0
    for event in events:
        if is_login_failure(event):
            current += 1
            longest = max(longest, current)
        elif is_login_success(event):
            current = 0
    return float(longest)


def _risk_trend_slope(events: List[Dict[str, Any]]) -> float:
    points = []
    for index, event in enumerate(events):
        stamp = parse_event_timestamp(event)
        if stamp is None:
            continue
        points.append((index, float(event.get("risk_score", 0) or 0.0)))
    if len(points) < 2:
        return 0.0
    x = np.array([point[0] for point in points], dtype=float)
    y = np.array([point[1] for point in points], dtype=float)
    try:
        slope = np.polyfit(x, y, 1)[0]
        return float(slope)
    except Exception:
        return 0.0


def _burst_peak_count(events: List[Dict[str, Any]], window_minutes: int) -> float:
    stamps = [parse_event_timestamp(event) for event in events]
    stamps = [stamp for stamp in stamps if stamp is not None]
    if not stamps:
        return 0.0
    return float(_peak_events_in_window(stamps, window_minutes))


def _peak_events_in_window(stamps: List[datetime], window_minutes: int) -> int:
    ordered = sorted(stamps)
    if not ordered:
        return 0
    peak = 0
    left = 0
    window_seconds = float(max(1, window_minutes) * 60)
    for right, stamp in enumerate(ordered):
        while left <= right and (stamp - ordered[left]).total_seconds() > window_seconds:
            left += 1
        peak = max(peak, right - left + 1)
    return peak
