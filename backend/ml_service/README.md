# SAIPS ML Reproducibility

This folder contains the attribution ML pipeline, reproducible benchmark data, and evaluation scripts that feed `ml-evaluation.php`.

## What is here

- `requirements.txt`: Python dependencies.
- `train_models.py`: Trains anomaly + attack models.
- `evaluate_models.py`: Main benchmark evaluation and report generator.
- `run_adversarial_suite.py`: Adversarial robustness runs.
- `time_window_eval.py`: Train-on-earlier / test-on-later temporal evaluation.
- `cross_environment_eval.py`: Cross-environment generalization stress test.
- `export_benchmark_version.py`: Exports versioned dataset files into `datasets/`.
- `export_case_studies.py`: Exports top flagged cases as markdown reports.
- `feedback_labels.py`: Analyst labeling CLI (`true_positive`, `false_positive`, `needs_review`).
- `benchmark_dataset.py`: Dataset builder + loader for versioned files.
- `benchmark_pipeline.py`: Shared scoring + ablation logic.
- `dynamic_graph_model.py`: Lightweight temporal heterogeneous graph scorer.
- `feedback_store.py`: Persistent analyst feedback store.
- `report_utils.py`: Shared report writer.
- `DATASET.md`: Dataset details.
- `LIMITATIONS.md`: Honest current limits.
- `../../ML_RESEARCH.md`: Research framing.

## Quick start (Windows)

```powershell
cd backend/ml_service
py -3.11 -m pip install -r requirements.txt
py -3.11 export_benchmark_version.py
py -3.11 train_models.py
py -3.11 evaluate_models.py
py -3.11 run_adversarial_suite.py
py -3.11 time_window_eval.py
py -3.11 cross_environment_eval.py
py -3.11 export_case_studies.py --top-n 5
```

Then open `../../ml-evaluation.php`.

## Generated reports

- `reports/latest_training.json`
- `reports/latest_evaluation.json`
- `reports/latest_adversarial.json`
- `reports/latest_time_window_eval.json`
- `reports/latest_cross_environment.json`
- `reports/latest_dataset_export.json`
- `reports/latest_case_study_export.json`
- `reports/case_studies/*.md`

## Evaluation coverage

- Metrics: precision, recall, F1, ROC-AUC, false positives.
- Baselines: `isolation_forest`, `pca_reconstruction`, `one_class_svm`, `autoencoder`, `behavioral_ensemble`.
- Attribution modes: `graph_only`, `dynamic_graph_temporal`, `graph_plus_anomaly`, `temporal_graph_plus_anomaly`, `graph_plus_anomaly_feedback`, `graph_plus_anomaly_llm`.
- Adversarial scenarios: IP rotation, timing jitter, device spoofing, MFA failure noise.
- Explanation quality comparison: local explanations vs LLM explanations.
- Case-study export: top flagged incidents in markdown for portfolio/research evidence.

## Notes

- The benchmark is synthetic, reproducible, and intentionally small.
- Versioned benchmark files live in `datasets/` and are loaded first when available.
- Feedback labels are file-backed at `reports/analyst_feedback.json`.
- LLM narratives are optional; the deterministic local explanation path is always available.
