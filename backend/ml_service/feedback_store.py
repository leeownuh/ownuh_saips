from __future__ import annotations

import json
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List

from report_utils import REPORTS_DIR, ensure_reports_dir


FEEDBACK_FILE = REPORTS_DIR / "analyst_feedback.json"
VALID_LABELS = {"true_positive", "false_positive", "needs_review"}


def _read_feedback() -> Dict[str, Any]:
    ensure_reports_dir()
    if not FEEDBACK_FILE.exists():
        return {"cases": {}}
    raw = FEEDBACK_FILE.read_text(encoding="utf-8")
    if raw.strip() == "":
        return {"cases": {}}
    parsed = json.loads(raw)
    if not isinstance(parsed, dict):
        return {"cases": {}}
    cases = parsed.get("cases")
    if not isinstance(cases, dict):
        parsed["cases"] = {}
    return parsed


def _write_feedback(payload: Dict[str, Any]) -> None:
    ensure_reports_dir()
    payload["updated_at"] = datetime.now().isoformat(timespec="seconds")
    FEEDBACK_FILE.write_text(json.dumps(payload, indent=2), encoding="utf-8")


def list_feedback() -> Dict[str, Dict[str, Any]]:
    payload = _read_feedback()
    cases = payload.get("cases", {})
    return cases if isinstance(cases, dict) else {}


def set_feedback(case_id: str, label: str, note: str = "", analyst: str = "analyst") -> Dict[str, Any]:
    normalized_case = str(case_id).strip()
    normalized_label = str(label).strip().lower()
    if normalized_case == "":
        raise ValueError("case_id must not be empty")
    if normalized_label not in VALID_LABELS:
        raise ValueError(f"label must be one of {sorted(VALID_LABELS)}")

    payload = _read_feedback()
    cases = payload.setdefault("cases", {})
    entry = {
        "label": normalized_label,
        "note": str(note).strip(),
        "analyst": str(analyst).strip() or "analyst",
        "updated_at": datetime.now().isoformat(timespec="seconds"),
    }
    cases[normalized_case] = entry
    _write_feedback(payload)
    return entry


def feedback_multiplier(case_id: str) -> float:
    entry = list_feedback().get(case_id)
    if not isinstance(entry, dict):
        return 1.0
    label = str(entry.get("label", "")).lower()
    if label == "false_positive":
        return 0.7
    if label == "true_positive":
        return 1.08
    return 1.0


def feedback_summary() -> Dict[str, Any]:
    labels = list_feedback()
    label_values = [str(entry.get("label", "needs_review")).lower() for entry in labels.values() if isinstance(entry, dict)]
    total = len(label_values)
    tp = sum(1 for value in label_values if value == "true_positive")
    fp = sum(1 for value in label_values if value == "false_positive")
    review = sum(1 for value in label_values if value == "needs_review")
    return {
        "labeled_cases": total,
        "true_positive": tp,
        "false_positive": fp,
        "needs_review": review,
    }


def recent_feedback(limit: int = 12) -> List[Dict[str, Any]]:
    rows: List[Dict[str, Any]] = []
    for case_id, entry in list_feedback().items():
        if not isinstance(entry, dict):
            continue
        rows.append(
            {
                "case_id": case_id,
                "label": str(entry.get("label", "needs_review")),
                "note": str(entry.get("note", "")),
                "analyst": str(entry.get("analyst", "analyst")),
                "updated_at": str(entry.get("updated_at", "")),
            }
        )
    rows.sort(key=lambda row: row["updated_at"], reverse=True)
    return rows[: max(1, int(limit))]
