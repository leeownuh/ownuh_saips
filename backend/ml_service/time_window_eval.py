from __future__ import annotations

import json
from datetime import datetime
from typing import Any, Dict, List, Tuple

from anomaly_detector import AnomalyDetector
from attack_detector import AdversarialAttackDetector
from benchmark_dataset import build_benchmark_dataset
from benchmark_pipeline import evaluate_dataset
from report_utils import save_json_report


def _parse_stamp(text: str) -> datetime:
    for fmt in ("%Y-%m-%d %H:%M:%S.%f", "%Y-%m-%d %H:%M:%S"):
        try:
            return datetime.strptime(text, fmt)
        except ValueError:
            continue
    return datetime.fromisoformat(text.replace("Z", "+00:00")).replace(tzinfo=None)


def _case_time_bounds(case: Dict[str, Any]) -> Tuple[datetime, datetime]:
    stamps = []
    for event in case.get("events", []):
        created_at = str(event.get("created_at", "")).strip()
        if created_at == "":
            continue
        try:
            stamps.append(_parse_stamp(created_at))
        except ValueError:
            continue
    if not stamps:
        fallback = datetime(1970, 1, 1)
        return fallback, fallback
    return min(stamps), max(stamps)


def evaluate_time_windows(dataset: Dict[str, Any]) -> Dict[str, Any]:
    cases = list(dataset.get("test_cases", []))
    if len(cases) < 3:
        return {
            "windows": [],
            "note": "Not enough test cases for strict time-window evaluation.",
        }

    sorted_cases = sorted(cases, key=lambda case: _case_time_bounds(case)[0])
    split_ratios = [0.5, 0.67, 0.75]
    windows: List[Dict[str, Any]] = []
    flattened_train = list(dataset.get("train_events", []))

    for ratio in split_ratios:
        split_index = max(1, min(len(sorted_cases) - 1, int(len(sorted_cases) * ratio)))
        train_cases = sorted_cases[:split_index]
        test_cases = sorted_cases[split_index:]
        if not test_cases:
            continue

        train_events = list(flattened_train)
        for case in train_cases:
            train_events.extend(case.get("events", []))

        anomaly = AnomalyDetector()
        attack = AdversarialAttackDetector()
        anomaly.train(train_events)
        anomaly.save_models()
        attack.train(train_events)
        attack.save_model()

        report = evaluate_dataset(test_cases, anomaly, attack)
        train_start, train_end = _case_time_bounds(train_cases[0])[0], _case_time_bounds(train_cases[-1])[1]
        test_start, test_end = _case_time_bounds(test_cases[0])[0], _case_time_bounds(test_cases[-1])[1]

        windows.append(
            {
                "split_ratio": ratio,
                "train_case_count": len(train_cases),
                "test_case_count": len(test_cases),
                "train_event_count": len(train_events),
                "train_window": {
                    "start": train_start.isoformat(sep=" "),
                    "end": train_end.isoformat(sep=" "),
                },
                "test_window": {
                    "start": test_start.isoformat(sep=" "),
                    "end": test_end.isoformat(sep=" "),
                },
                "modes": report.get("modes", {}),
                "anomaly_models": report.get("anomaly_models", {}),
            }
        )

    return {
        "windows": windows,
        "dataset_version": dataset.get("dataset_version", "v1"),
        "dataset_source": dataset.get("dataset_source", "unknown"),
    }


def main() -> None:
    dataset = build_benchmark_dataset()
    report = evaluate_time_windows(dataset)
    report["report_path"] = save_json_report("latest_time_window_eval.json", report)
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
