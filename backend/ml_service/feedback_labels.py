from __future__ import annotations

import argparse
import json
import sys

from feedback_store import list_feedback, recent_feedback, set_feedback
from report_utils import save_json_report


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Manage analyst feedback labels for attribution cases.")
    subparsers = parser.add_subparsers(dest="command", required=True)

    set_parser = subparsers.add_parser("set", help="Set label for a case.")
    set_parser.add_argument("case_id", type=str, help="Case ID to label.")
    set_parser.add_argument("label", type=str, choices=["true_positive", "false_positive", "needs_review"], help="Analyst label.")
    set_parser.add_argument("--note", type=str, default="", help="Optional analyst note.")
    set_parser.add_argument("--analyst", type=str, default="analyst", help="Analyst identifier.")

    subparsers.add_parser("list", help="List all labels.")
    subparsers.add_parser("recent", help="Show recent labels.")

    return parser.parse_args()


def main() -> None:
    args = parse_args()

    if args.command == "set":
        entry = set_feedback(args.case_id, args.label, note=args.note, analyst=args.analyst)
        payload = {
            "status": "success",
            "case_id": args.case_id,
            "entry": entry,
        }
        payload["report_path"] = save_json_report("latest_feedback_update.json", payload)
        print(json.dumps(payload, indent=2))
        return

    if args.command == "list":
        print(json.dumps({"cases": list_feedback()}, indent=2))
        return

    if args.command == "recent":
        print(json.dumps({"recent": recent_feedback()}, indent=2))
        return

    print(json.dumps({"status": "error", "message": "Unknown command."}))
    sys.exit(1)


if __name__ == "__main__":
    main()
