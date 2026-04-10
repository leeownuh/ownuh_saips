from __future__ import annotations

import json
from datetime import datetime
from pathlib import Path
from typing import Any, Dict


REPORTS_DIR = Path(__file__).parent / "reports"


def ensure_reports_dir() -> Path:
    REPORTS_DIR.mkdir(exist_ok=True)
    return REPORTS_DIR


def save_json_report(filename: str, payload: Dict[str, Any]) -> str:
    reports_dir = ensure_reports_dir()
    report_path = reports_dir / filename
    data = {
        **payload,
        "generated_at": datetime.now().isoformat(timespec="seconds"),
    }
    report_path.write_text(json.dumps(data, indent=2), encoding="utf-8")
    return str(report_path)

