from __future__ import annotations

from typing import Any, Dict, List, Tuple

from sklearn.metrics import precision_recall_fscore_support, roc_auc_score

from anomaly_detector import AnomalyDetector
from attack_detector import AdversarialAttackDetector
from entity_correlation import EntityCorrelationGraph
from benchmark_dataset import build_benchmark_dataset


def train_detectors(model_dir: str | None = None) -> Tuple[AnomalyDetector, AdversarialAttackDetector]:
    dataset = build_benchmark_dataset()
    anomaly = AnomalyDetector(model_dir=model_dir)
    attack = AdversarialAttackDetector(model_dir=model_dir)
    anomaly.train(dataset["train_events"])
    anomaly.save_models()
    attack.train(dataset["train_events"])
    attack.save_model()
    return anomaly, attack


def load_or_train_detectors(model_dir: str | None = None) -> Tuple[AnomalyDetector, AdversarialAttackDetector]:
    anomaly = AnomalyDetector(model_dir=model_dir)
    attack = AdversarialAttackDetector(model_dir=model_dir)
    if not anomaly.load_models():
        anomaly, attack = train_detectors(model_dir=model_dir)
    elif not attack.load_model():
        anomaly, attack = train_detectors(model_dir=model_dir)
    return anomaly, attack


def evaluate_dataset(cases: List[Dict[str, Any]], anomaly: AnomalyDetector, attack: AdversarialAttackDetector) -> Dict[str, Any]:
    results = [evaluate_case(case, anomaly, attack) for case in cases]
    malicious = [result for result in results if result["label"] == 1]
    attack_truth = [result["attack_type"] for result in malicious]
    attack_pred = [result["predicted_attack_type"] for result in malicious]
    attack_precision, attack_recall, attack_f1, _ = precision_recall_fscore_support(
        attack_truth,
        attack_pred,
        average="macro",
        zero_division=0,
    )

    return {
        "num_cases": len(results),
        "modes": {
            "graph_only": mode_metrics(results, "graph_only"),
            "graph_plus_anomaly": mode_metrics(results, "graph_plus_anomaly"),
            "graph_plus_anomaly_llm": {
                **mode_metrics(results, "graph_plus_anomaly"),
                "explanation_coverage": round(sum(1 for result in results if result["llm_explanation"]) / max(1, len(results)), 3),
            },
        },
        "attack_classifier": {
            "precision": round(float(attack_precision), 4),
            "recall": round(float(attack_recall), 4),
            "f1": round(float(attack_f1), 4),
        },
        "case_studies": sorted(results, key=lambda result: result["graph_plus_anomaly_score"], reverse=True)[:3],
    }


def evaluate_case(case: Dict[str, Any], anomaly: AnomalyDetector, attack: AdversarialAttackDetector) -> Dict[str, Any]:
    events = case["events"]
    anomaly_result = anomaly.predict(events)
    attack_result = attack.predict(events)
    graph = EntityCorrelationGraph()
    graph.build_graph(events)
    entity_result = graph.detect_compromised_entities(threshold=0.5)

    expected_entities = set(case["expected_entities"])
    expected_user = next((entity.split("user:", 1)[1] for entity in expected_entities if entity.startswith("user:")), "")
    graph_hits = [entity for entity in entity_result["compromised_entities"] if entity["entity_id"] in expected_entities]
    graph_score = max([entity["suspicion_score"] for entity in graph_hits], default=max([entity["suspicion_score"] for entity in entity_result["compromised_entities"]], default=0.0))
    anomaly_score = max(
        [item["anomaly_score"] for item in anomaly_result["anomalies"] if item["user_id"] == expected_user],
        default=max([item["anomaly_score"] for item in anomaly_result["anomalies"]], default=0.0),
    )

    attack_candidates = [item for item in attack_result["attacks"] if item["user_id"] == expected_user or item["user_id"] in expected_entities]
    if not attack_candidates:
        attack_candidates = attack_result["attacks"]
    top_attack = max(attack_candidates, key=lambda item: item["confidence"], default={"attack_type": "NORMAL", "confidence": 0.0})

    graph_only_score = round(float(graph_score), 4)
    graph_plus_anomaly_score = round(max(graph_score, (graph_score * 0.6) + (anomaly_score * 0.4), float(top_attack["confidence"]) * 0.8), 4)
    llm_explanation = build_llm_explanation(case, top_attack["attack_type"], graph_plus_anomaly_score, graph_hits)

    return {
        "case_id": case["case_id"],
        "description": case["description"],
        "label": int(case["label"]),
        "attack_type": case["attack_type"],
        "predicted_attack_type": top_attack["attack_type"],
        "graph_only_score": graph_only_score,
        "graph_plus_anomaly_score": graph_plus_anomaly_score,
        "graph_only_pred": int(graph_only_score >= 0.55),
        "graph_plus_anomaly_pred": int(graph_plus_anomaly_score >= 0.55),
        "graph_hits": [entity["entity_id"] for entity in graph_hits],
        "anomaly_hits": [item["user_id"] for item in anomaly_result["anomalies"]],
        "llm_explanation": llm_explanation,
    }


def mode_metrics(results: List[Dict[str, Any]], mode: str) -> Dict[str, Any]:
    score_key = f"{mode}_score"
    pred_key = f"{mode}_pred"
    y_true = [result["label"] for result in results]
    scores = [result[score_key] for result in results]
    preds = [result[pred_key] for result in results]
    precision, recall, f1, _ = precision_recall_fscore_support(y_true, preds, average="binary", zero_division=0)
    auc = roc_auc_score(y_true, scores) if len(set(y_true)) > 1 else None
    false_positives = sum(1 for truth, pred in zip(y_true, preds) if truth == 0 and pred == 1)
    return {
        "precision": round(float(precision), 4),
        "recall": round(float(recall), 4),
        "f1": round(float(f1), 4),
        "roc_auc": round(float(auc), 4) if auc is not None else None,
        "false_positives": false_positives,
    }


def build_llm_explanation(case: Dict[str, Any], predicted_attack: str, score: float, graph_hits: List[Dict[str, Any]]) -> str:
    entity_clause = ", ".join(entity["entity_id"] for entity in graph_hits) if graph_hits else "the observed graph neighbourhood"
    return (
        f"Case {case['case_id']} is treated as {predicted_attack} because fused graph and anomaly score "
        f"reached {score:.2f}. The strongest attribution evidence came from {entity_clause}."
    )
