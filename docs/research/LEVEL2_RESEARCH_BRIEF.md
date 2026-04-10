# SAIPS Level 2 Research Brief

Date: April 10, 2026  
Project: Ownuh SAIPS  
Focus: Explainable attack attribution for enterprise authentication and audit logs

## 1. Problem

Credential-valid attacks and compromised insiders can appear benign when judged by a single signal (for example, valid login, normal schedule, or known IP). This project addresses that gap by combining multiple weak signals into a single attribution decision with analyst-facing evidence.

## 2. Research Objective

Evaluate whether a fused pipeline (graph linking + anomaly modeling + attack classification) performs better than graph-only attribution while remaining interpretable and robust under adversarial conditions.

## 3. System (Level 2)

The current Level 2 pipeline includes:

- Graph-based linking across users, devices, IPs, countries, and incidents.
- Behavioral anomaly detection using:
  - Isolation Forest
  - PCA reconstruction
  - One-Class SVM
  - Autoencoder-style MLP
  - Behavioral ensemble score
- Attack-type classification for security scenarios.
- Temporal behavior features (29 total features), including:
  - failed-login short-window velocity
  - burst peak behavior
  - recent-window failure ratio
  - consecutive failure streak
  - risk trend slope
- Analyst feedback loop with case labels:
  - `true_positive`
  - `false_positive`
  - `needs_review`
- Explanation quality scoring and analyst-facing evaluation dashboard.

## 4. Dataset and Evaluation Setup

- Training events: 22
- Training users: 10
- Test cases: 6
- Positive cases: 4
- Negative cases: 2
- Benchmark type: synthetic, reproducible

Important: current results are benchmark evidence, not production validation.

## 5. Current Results

### Pipeline Ablation

- `graph_only`: F1 = 0.0000, ROC-AUC = 0.5000
- `graph_plus_anomaly`: F1 = 1.0000, ROC-AUC = 1.0000
- `graph_plus_anomaly_feedback`: F1 = 1.0000, ROC-AUC = 1.0000
- `graph_plus_anomaly_llm`: F1 = 1.0000, ROC-AUC = 1.0000, explanation coverage = 1.0000

### Anomaly Model Comparison

- Isolation Forest: F1 = 0.6667
- PCA reconstruction: F1 = 0.5000
- One-Class SVM: F1 = 0.2857
- Autoencoder: F1 = 0.2857
- Behavioral ensemble: F1 = 0.2857

### Adversarial Robustness (`graph_plus_anomaly` mode)

- Baseline recall: 1.0000
- IP rotation recall: 0.7500 (delta: -0.2500)
- Timing jitter recall: 1.0000
- Device spoofing recall: 1.0000
- MFA-failure noise recall: 1.0000

### Feedback and Explanation Quality

- Labeled cases: 2
- Feedback distribution: 1 true positive, 1 false positive
- Explanation quality:
  - attack alignment: 1.0000
  - entity alignment: 1.0000
  - focus-user alignment: 1.0000
  - overall: 1.0000

## 6. Interpretation

- Graph-only linking is insufficient by itself in this benchmark.
- Fused attribution pipeline is substantially stronger than graph-only.
- IP rotation remains the main adversarial weakness.
- Feedback infrastructure is now in place, enabling iterative analyst-in-the-loop improvement.

## 7. Limitations

- Small synthetic benchmark limits external validity.
- Perfect fused scores can overstate expected production performance.
- Feedback label volume is currently low.
- Explanation quality metric is heuristic and should be validated against human analyst judgment.

## 8. Next Research Steps

- Evaluate on larger, noisier, time-split enterprise-style logs.
- Increase analyst-labeled case volume and measure false positives at scale.
- Improve robustness against IP rotation and distributed evasion.
- Compare explanation usefulness via analyst task outcomes.

## 9. Reproducibility

Run:

```bash
cd backend/ml_service
python train_models.py
python evaluate_models.py
python run_adversarial_suite.py
```

Review:

- `backend/ml_service/reports/latest_training.json`
- `backend/ml_service/reports/latest_evaluation.json`
- `backend/ml_service/reports/latest_adversarial.json`
- `ml-evaluation.php`
