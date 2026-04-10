from __future__ import annotations

import argparse
import json

from benchmark_dataset import DATASET_VERSION, build_benchmark_dataset, save_versioned_dataset
from report_utils import save_json_report


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export benchmark dataset into versioned JSON files.")
    parser.add_argument("--version", default=DATASET_VERSION, help="Dataset version tag (default: v1).")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    dataset = build_benchmark_dataset(prefer_versioned=False, version=args.version)
    paths = save_versioned_dataset(dataset, version=args.version)
    payload = {
        "status": "success",
        "dataset_version": args.version,
        "train_events": len(dataset.get("train_events", [])),
        "test_cases": len(dataset.get("test_cases", [])),
        "files": paths,
    }
    payload["report_path"] = save_json_report("latest_dataset_export.json", payload)
    print(json.dumps(payload, indent=2))


if __name__ == "__main__":
    main()
