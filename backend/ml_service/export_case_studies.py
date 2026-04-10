from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any, Dict, List

from report_utils import REPORTS_DIR, save_json_report


def _load_latest_evaluation() -> Dict[str, Any]:
    path = REPORTS_DIR / "latest_evaluation.json"
    if not path.exists():
        return {}
    raw = path.read_text(encoding="utf-8")
    parsed = json.loads(raw)
    return parsed if isinstance(parsed, dict) else {}


def _render_case_markdown(case: Dict[str, Any]) -> str:
    drivers = case.get("behavioral_drivers", [])
    drivers_text = "\n".join(
        f"- {item.get('feature', 'feature')}: {item.get('value', 0)}"
        for item in drivers
        if isinstance(item, dict)
    ) or "- none"

    return "\n".join(
        [
            f"# Case Study: {case.get('case_id', 'unknown')}",
            "",
            f"- True label: `{case.get('attack_type', 'UNKNOWN')}`",
            f"- Predicted label: `{case.get('predicted_attack_type', 'UNKNOWN')}`",
            f"- Fused score: `{case.get('graph_plus_anomaly_score', 0)}`",
            f"- Temporal fused score: `{case.get('temporal_graph_plus_anomaly_score', 0)}`",
            f"- Feedback label: `{case.get('feedback_label', 'unlabeled')}`",
            "",
            "## Description",
            str(case.get("description", "")),
            "",
            "## Local Explanation",
            str(case.get("local_explanation", "")),
            "",
            "## LLM Explanation",
            str(case.get("llm_explanation", "")),
            "",
            "## Top Behavioral Drivers",
            drivers_text,
            "",
            "## Linked Entities",
            ", ".join(case.get("graph_hits", [])) or "none",
            "",
            "## Notes",
            "Generated automatically from latest evaluation report.",
            "",
        ]
    )


def export_case_studies(top_n: int = 5, evaluation: Dict[str, Any] | None = None) -> Dict[str, Any]:
    evaluation = evaluation or _load_latest_evaluation()
    cases = list(evaluation.get("case_studies", []))
    if not cases:
        return {"status": "error", "message": "No case studies found in latest_evaluation.json."}

    output_dir = REPORTS_DIR / "case_studies"
    output_dir.mkdir(exist_ok=True)
    chosen = cases[: max(1, min(10, int(top_n)))]
    exported_files: List[str] = []

    index_lines = ["# Exported Case Studies", ""]
    for index, case in enumerate(chosen, start=1):
        case_id = str(case.get("case_id", f"case_{index}"))
        filename = f"{index:02d}_{case_id}.md"
        path = output_dir / filename
        path.write_text(_render_case_markdown(case), encoding="utf-8")
        exported_files.append(str(path))
        index_lines.append(f"- [{case_id}]({filename})")

    (output_dir / "INDEX.md").write_text("\n".join(index_lines) + "\n", encoding="utf-8")

    return {
        "status": "success",
        "count": len(exported_files),
        "output_dir": str(output_dir),
        "files": exported_files,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Export top case studies to markdown files.")
    parser.add_argument("--top-n", type=int, default=5, help="Number of case studies to export (default: 5).")
    args = parser.parse_args()

    result = export_case_studies(top_n=args.top_n)
    result["report_path"] = save_json_report("latest_case_study_export.json", result)
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()
