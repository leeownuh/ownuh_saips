# SAIPS Level 2 Research Brief

Date: April 10, 2026  
Project: Ownuh SAIPS  
Focus: Explainable attack attribution for enterprise authentication and audit logs

## 1. Problem

Credential-valid attacks and compromised insiders can look benign when evaluated by any single signal (valid credentials, normal login time, or clean IP). SAIPS addresses this by fusing weak distributed evidence into attribution decisions with analyst-facing explanations.

## 2. Research Objective

Evaluate whether fused attribution (graph + anomaly + classifier) outperforms graph-only correlation while remaining interpretable and reasonably robust under adversarial behavior.

## 3. Level 2 System

Current Level 2 includes:

- Graph/entity correlation across users, devices, IPs, countries, sessions, and incidents.
- Temporal graph baseline with recency-decayed heterogeneous link scoring.
- Behavioral anomaly modeling (`isolation_forest`, `pca_reconstruction`, `one_class_svm`, `autoencoder`, `behavioral_ensemble`).
- Attack-type classification for suspicious authentication patterns.
- Temporal behavioral feature set (29 features), including failed-login velocity, burstiness, recent failure ratio, risk trend slope, MFA-failure indicators, and session reuse signals.
- Analyst feedback loop (`true_positive`, `false_positive`, `needs_review`) used by feedback-aware scoring.
- Explanation quality scoring (local vs LLM narrative alignment) and dashboard evidence.

## 4. Dataset and Setup

- Training events: 22
- Training users: 10
- Test cases: 6
- Positive cases: 4
- Negative cases: 2
- Dataset: synthetic and versioned (`datasets/v1_train_events.json`, `datasets/v1_test_cases.json`)

Important: results are reproducible benchmark evidence, not production validation.

## 5. Latest Results (from `latest_evaluation.json` / `latest_adversarial.json`)

### Pipeline Modes

- `graph_only`: F1 0.0000, ROC-AUC 0.5000, false positives 0
- `dynamic_graph_temporal`: F1 0.8000, ROC-AUC 0.8750, false positives 2
- `graph_plus_anomaly`: F1 1.0000, ROC-AUC 1.0000, false positives 0
- `temporal_graph_plus_anomaly`: F1 0.8000, ROC-AUC 0.3750, false positives 2
- `graph_plus_anomaly_feedback`: F1 1.0000, ROC-AUC 1.0000, false positives 0
- `graph_plus_anomaly_llm`: F1 1.0000, ROC-AUC 1.0000, explanation coverage 1.0000

### Anomaly Baselines

- Isolation Forest: F1 0.6667
- PCA reconstruction: F1 0.5000
- One-Class SVM: F1 0.2857
- Autoencoder: F1 0.2857
- Behavioral ensemble: F1 0.2857

### Adversarial Robustness

`graph_plus_anomaly` mode:

- Baseline recall: 1.0000
- IP rotation recall: 0.7500 (delta -0.2500)
- Timing jitter recall: 1.0000 (delta 0.0000)
- Device spoofing recall: 1.0000 (delta 0.0000)
- MFA-failure noise recall: 1.0000 (delta 0.0000)

`temporal_graph_plus_anomaly` mode:

- Baseline recall: 1.0000
- IP rotation recall: 0.7500 (delta -0.2500)
- Timing jitter recall: 1.0000 (delta 0.0000)
- Device spoofing recall: 1.0000 (delta 0.0000)
- MFA-failure noise recall: 1.0000 (delta 0.0000)

### Time-Window and Cross-Environment

- Time-window splits (0.50 / 0.67 / 0.75) are included and reproducible in `latest_time_window_eval.json`.
- Cross-environment checks (`global_enterprise`, `remote_workforce`, `high_mfa_org`) are included in `latest_cross_environment.json`.

### Feedback and Explanation

- Labeled cases: 2 (1 true positive, 1 false positive, 0 needs review)
- Explanation quality: local and LLM alignment both report 1.0000 overall on this benchmark run.

## 6. Interpretation

- Static graph-only attribution is insufficient in this benchmark.
- Fused pipeline modes substantially improve detection.
- IP rotation is the main observed robustness weakness.
- Feedback and explanation infrastructure are fully integrated and demo-ready.

## 7. Limitations

- Small synthetic benchmark limits external validity.
- Near-perfect scores on synthetic cases can overstate expected real-world performance.
- Feedback label volume is low.
- Explanation quality metrics are still heuristic and should be paired with analyst studies.

## 8. Next Research Steps

- Expand to larger enterprise-like logs with stronger temporal drift.
- Increase analyst-labeled volume and evaluate ranking quality at triage depth.
- Improve robustness specifically for IP rotation and distributed evasion.
- Add calibration analysis and error analysis by attack type.

## 9. Reproducibility

```powershell
cd backend/ml_service
py -3.11 export_benchmark_version.py
py -3.11 train_models.py
py -3.11 evaluate_models.py
py -3.11 run_adversarial_suite.py
py -3.11 time_window_eval.py
py -3.11 cross_environment_eval.py
py -3.11 export_case_studies.py --top-n 5
```

Review:

- `backend/ml_service/reports/latest_training.json`
- `backend/ml_service/reports/latest_evaluation.json`
- `backend/ml_service/reports/latest_adversarial.json`
- `backend/ml_service/reports/latest_time_window_eval.json`
- `backend/ml_service/reports/latest_cross_environment.json`
- `backend/ml_service/reports/case_studies/INDEX.md`
- `ml-evaluation.php`
