# SAIPS ML Reproducibility

This folder contains the security-analytics models plus a small, reproducible benchmark harness for portfolio and research use.

## What is here

- `requirements.txt`: Python dependencies for the ML pipeline.
- `train_models.py`: Trains the anomaly and attack models on the bundled synthetic benchmark training events.
- `evaluate_models.py`: Runs case-level evaluation and ablation reporting.
- `run_adversarial_suite.py`: Tests robustness under IP rotation, timing jitter, device spoofing, and MFA-noise mutations.
- `feedback_labels.py`: Stores analyst labels for surfaced cases (`true_positive`, `false_positive`, `needs_review`).
- `benchmark_dataset.py`: Builds the synthetic training and test dataset.
- `benchmark_pipeline.py`: Shared evaluation helpers.
- `feedback_store.py`: Persistent analyst-feedback store used by evaluation.
- `report_utils.py`: Writes reusable JSON report files for the dashboard.
- `reports/latest_training.json`: Latest training summary.
- `reports/latest_evaluation.json`: Latest benchmark + ablation summary.
- `reports/latest_adversarial.json`: Latest adversarial robustness summary.
- `DATASET.md`: Documents how the benchmark data is generated.
- `LIMITATIONS.md`: Records the current research and engineering limits honestly.
- `../../ML_RESEARCH.md`: Research-facing framing for supervisor outreach and paper positioning.

## Quick start

```bash
cd backend/ml_service
python -m pip install -r requirements.txt
python train_models.py
python evaluate_models.py
python run_adversarial_suite.py
python feedback_labels.py set account_takeover_travel true_positive --analyst lead_analyst
```

After running, open `../../ml-evaluation.php` in the app to review the latest results.
You can also label cases directly in `../../attack-attribution.php` to feed the feedback-aware mode.

## Evaluation outputs

`evaluate_models.py` reports:

- Precision
- Recall
- F1
- ROC-AUC
- False positives
- Attack-classifier macro metrics
- Case studies
- Explanation quality metrics (attack/entity/focus alignment)
- Feedback-aware ablation mode:
  - `graph_plus_anomaly_feedback`
- Anomaly baseline comparison for:
  - `isolation_forest`
  - `pca_reconstruction`
  - `one_class_svm`
  - `autoencoder`
  - `behavioral_ensemble`
- Ablation outputs for:
  - `graph_only`
  - `graph_plus_anomaly`
  - `graph_plus_anomaly_llm`

## Notes

- The benchmark is synthetic and reproducible, not production traffic.
- The feedback loop is file-backed in `reports/analyst_feedback.json` so it is portable in local demo environments.
- The third ablation mode keeps the same detection score as `graph_plus_anomaly` and measures explanation coverage separately. This is deliberate so the benchmark stays runnable offline.
- Live LLM narratives in the web app are optional and depend on external provider access, quota, and API configuration.
- The core attribution pipeline, evaluation scripts, and adversarial suite remain usable without paid model access because the product falls back to deterministic local explanations.
