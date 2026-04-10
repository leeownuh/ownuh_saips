# Documentation Map

This folder is the fastest way to navigate the SAIPS project narrative for demos, applications, and technical review.

## Start here

- `../START_HERE.md`
  One-page orientation for product + ML + research framing.
- `../README.md`
  Product overview, setup, screenshots, and deployment notes.

## Research docs

- `research/LEVEL2_RESEARCH_BRIEF.md`
  Current Level 2 method, metrics, interpretation, and limitations.
- `research/RESULTS_SNAPSHOT_2026-04-10.md`
  Frozen metrics snapshot from the latest reproducible evaluation run.
- `../ML_RESEARCH.md`
  Positioning for applications and supervisor outreach.

## Application docs

- `application/DR_CHOO_OUTREACH_PACK.md`
  CV bullets, email draft, SOP paragraph, and claims guardrails.

## ML reproducibility docs

- `../backend/ml_service/README.md`
  Script runbook and report outputs.
- `../backend/ml_service/DATASET.md`
  Versioned dataset structure and export workflow.
- `../backend/ml_service/LIMITATIONS.md`
  Honest limitations and next research steps.

## Re-run checklist

Use this before interviews, outreach, or demos:

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

Then verify:

- `../backend/ml_service/reports/latest_training.json`
- `../backend/ml_service/reports/latest_evaluation.json`
- `../backend/ml_service/reports/latest_adversarial.json`
- `../backend/ml_service/reports/latest_time_window_eval.json`
- `../backend/ml_service/reports/latest_cross_environment.json`
- `../backend/ml_service/reports/case_studies/INDEX.md`

