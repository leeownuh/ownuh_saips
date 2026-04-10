# SAIPS Results Snapshot (2026-04-10)

Source reports:

- `backend/ml_service/reports/latest_training.json` (generated at `2026-04-10T12:02:30`)
- `backend/ml_service/reports/latest_evaluation.json` (generated at `2026-04-10T12:03:23`)
- `backend/ml_service/reports/latest_adversarial.json` (generated at `2026-04-10T12:04:10`)

## Dataset summary

- Train events: 22
- Train users: 10
- Feature count: 29
- Test cases: 6
- Positive cases: 4
- Negative cases: 2
- Dataset version/source: `v1` / `versioned_file`

## Pipeline mode metrics

| Mode | Precision | Recall | F1 | ROC-AUC | False Positives |
|---|---:|---:|---:|---:|---:|
| graph_only | 0.0000 | 0.0000 | 0.0000 | 0.5000 | 0 |
| dynamic_graph_temporal | 0.6667 | 1.0000 | 0.8000 | 0.8750 | 2 |
| graph_plus_anomaly | 1.0000 | 1.0000 | 1.0000 | 1.0000 | 0 |
| temporal_graph_plus_anomaly | 0.6667 | 1.0000 | 0.8000 | 0.3750 | 2 |
| graph_plus_anomaly_feedback | 1.0000 | 1.0000 | 1.0000 | 1.0000 | 0 |
| graph_plus_anomaly_llm | 1.0000 | 1.0000 | 1.0000 | 1.0000 | 0 |

Additional:

- LLM explanation coverage (`graph_plus_anomaly_llm`): 1.0000
- Attack classifier (macro): precision 1.0000, recall 1.0000, F1 1.0000

## Anomaly baseline metrics

| Baseline | Precision | Recall | F1 | ROC-AUC | False Positives |
|---|---:|---:|---:|---:|---:|
| isolation_forest | 0.6000 | 0.7500 | 0.6667 | 0.7500 | 2 |
| pca_reconstruction | 0.5000 | 0.5000 | 0.5000 | 0.2500 | 2 |
| one_class_svm | 0.3333 | 0.2500 | 0.2857 | 0.1250 | 2 |
| autoencoder | 0.3333 | 0.2500 | 0.2857 | 0.1875 | 2 |
| behavioral_ensemble | 0.3333 | 0.2500 | 0.2857 | 0.2500 | 2 |

## Adversarial summary

Main weakness across both fused modes:

- IP rotation: recall delta -0.2500 versus baseline

Stable scenarios:

- Timing jitter: recall delta 0.0000
- Device spoofing: recall delta 0.0000
- MFA failure noise: recall delta 0.0000

## Feedback and explanation

- Labeled cases: 2
- Feedback split: 1 true positive, 1 false positive, 0 needs review
- Explanation quality local overall: 1.0000
- Explanation quality LLM overall: 1.0000
- Delta over local: 0.0000

## Scope warning

These values are reproducible benchmark outputs on a small synthetic dataset. They are useful for method comparison and documentation, not as production-validation claims.

