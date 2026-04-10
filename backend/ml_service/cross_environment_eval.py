from __future__ import annotations

import json
from copy import deepcopy
from datetime import datetime, timedelta
from typing import Any, Dict, List

from benchmark_dataset import build_benchmark_dataset
from benchmark_pipeline import evaluate_dataset, load_or_train_detectors
from report_utils import save_json_report


def _clone_cases(cases: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    return deepcopy(cases)


def mutate_global_enterprise(cases: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    mutated = _clone_cases(cases)
    benign_geo_cycle = ["US", "DE", "SG", "AE"]
    for case in mutated:
        if int(case.get("label", 0)) != 0:
            continue
        for index, event in enumerate(case.get("events", [])):
            if str(event.get("event_code", "")).upper() == "AUTH-001":
                event["country_code"] = benign_geo_cycle[index % len(benign_geo_cycle)]
                event["risk_score"] = max(5, int(event.get("risk_score", 10)) - 4)
    return mutated


def mutate_remote_workforce(cases: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    mutated = _clone_cases(cases)
    for case in mutated:
        if int(case.get("label", 0)) != 0:
            continue
        for index, event in enumerate(case.get("events", [])):
            created_at = str(event.get("created_at", "")).strip()
            if created_at == "":
                continue
            stamp = datetime.strptime(created_at, "%Y-%m-%d %H:%M:%S")
            shifted = stamp + timedelta(hours=9 if index % 2 == 0 else -7)
            event["created_at"] = shifted.strftime("%Y-%m-%d %H:%M:%S")
            event["risk_score"] = max(4, int(event.get("risk_score", 10)) - 3)
    return mutated


def mutate_high_mfa_org(cases: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    mutated = _clone_cases(cases)
    next_id = 9500
    for case in mutated:
        for event in case.get("events", []):
            if str(event.get("event_code", "")).upper() == "AUTH-001":
                event["mfa_method"] = "totp"
        if int(case.get("label", 0)) == 0:
            base_event = case["events"][0]
            noise = dict(base_event)
            noise["id"] = next_id
            noise["event_code"] = "AUTH-002"
            noise["event_name"] = "Failed Login Attempt"
            noise["risk_score"] = min(35, int(noise.get("risk_score", 20)) + 5)
            noise["details"] = {"reason": "user_typo", "username": noise.get("user_id", "unknown")}
            case["events"].append(noise)
            next_id += 1
    return mutated


def evaluate_cross_environment(
    dataset: Dict[str, Any] | None = None,
    anomaly=None,
    attack=None,
) -> Dict[str, Any]:
    dataset = dataset or build_benchmark_dataset()
    if anomaly is None or attack is None:
        anomaly, attack = load_or_train_detectors()
    base = evaluate_dataset(dataset["test_cases"], anomaly, attack)

    environments = {
        "baseline": dataset["test_cases"],
        "global_enterprise": mutate_global_enterprise(dataset["test_cases"]),
        "remote_workforce": mutate_remote_workforce(dataset["test_cases"]),
        "high_mfa_org": mutate_high_mfa_org(dataset["test_cases"]),
    }

    report_envs: Dict[str, Any] = {}
    baseline_f1 = float(base["modes"]["temporal_graph_plus_anomaly"]["f1"])
    for name, cases in environments.items():
        result = base if name == "baseline" else evaluate_dataset(cases, anomaly, attack)
        metrics = result["modes"]["temporal_graph_plus_anomaly"]
        report_envs[name] = {
            "precision": metrics["precision"],
            "recall": metrics["recall"],
            "f1": metrics["f1"],
            "roc_auc": metrics["roc_auc"],
            "f1_delta_vs_baseline": round(float(metrics["f1"]) - baseline_f1, 4),
        }

    return {
        "dataset_version": dataset.get("dataset_version", "v1"),
        "mode": "temporal_graph_plus_anomaly",
        "environments": report_envs,
    }


def main() -> None:
    report = evaluate_cross_environment()
    report["report_path"] = save_json_report("latest_cross_environment.json", report)
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
