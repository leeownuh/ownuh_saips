# START HERE

This repository now includes a complete, defensible AI/ML security-analysis lane built around attack attribution rather than generic "AI features."

## What is actually in the repo

### Product-facing ML integration

- `backend/Services/MLService.php`
  Orchestrates anomaly detection, attack classification, entity correlation, and attack-attribution case generation.
- `backend/api/ml_anomalies.php`
  Authenticated API endpoint for anomaly detection.
- `backend/api/ml_attacks.php`
  Authenticated API endpoint for attack classification.
- `backend/api/ml_entities.php`
  Authenticated API endpoint for entity detection and graph metrics.
- `backend/api/ml_attribution.php`
  Authenticated API endpoint for fused attack-attribution analysis.
- `attack-attribution.php`
  Dashboard page for analyst-facing attribution cases, relationship-link visuals, and incident handoff.
- `backend/attribution_visuals.php`
  Shared SVG graph helpers for the attack-attribution dashboard's relationship views.
- `backend/Services/AIService.php`
  Provides executive reporting plus optional structured LLM narratives for top attribution cases when an OpenAI-compatible provider is configured and available.

### Reproducibility and evaluation

- `backend/ml_service/requirements.txt`
  Python dependencies.
- `backend/ml_service/train_models.py`
  Trains the anomaly and attack models on the synthetic benchmark data.
- `backend/ml_service/evaluate_models.py`
  Reports precision, recall, F1, ROC-AUC, false positives, attack metrics, case studies, and pipeline ablations.
- `backend/ml_service/run_adversarial_suite.py`
  Stress-tests the pipeline under IP rotation, timing jitter, device spoofing, and MFA-failure noise.
- `backend/ml_service/benchmark_dataset.py`
  Synthetic benchmark dataset generator.
- `backend/ml_service/benchmark_pipeline.py`
  Shared evaluation and ablation logic.
- `backend/ml_service/DATASET.md`
  Honest description of the bundled benchmark data.
- `backend/ml_service/LIMITATIONS.md`
  Honest limitations and next-step research gaps.
- `backend/ml_service/README.md`
  Quick-start guide for the ML reproducibility folder.
- `ML_RESEARCH.md`
  Research-facing framing: problem statement, method, baselines, claims, and next-step agenda.

## Research storyline

The strongest way to present this project is:

`Explainable Graph-Based Attack Attribution for Enterprise Authentication and Audit Logs`

The pipeline combines:

1. Graph/entity correlation
2. User-level anomaly detection
3. Attack-type classification
4. Visual graph-based linking across users, devices, IPs, and incidents
5. Optional LLM-generated analyst narratives for top cases
6. Incident creation from surfaced cases

That is much closer to Dr. Choo's research direction than adding a chatbot or unrelated AI widget.

## How to run the web feature

1. Serve the PHP app through XAMPP or another local PHP server.
2. Sign in as an admin user.
3. Open `attack-attribution.php`.
4. Adjust the date window and event limit.
5. Optionally enable `LLM mode` if an OpenAI-compatible key is configured in `backend/config/.env` and the provider has available quota.

Notes:

- If Python models are unavailable, the PHP layer falls back to heuristic scoring.
- If no OpenAI-compatible API key is configured, or the external provider is unavailable, rate-limited, or out of quota, the dashboard keeps the deterministic local explanation path and will say so.

## How to run the evaluation

```bash
cd backend/ml_service
python -m pip install -r requirements.txt
python train_models.py
python evaluate_models.py
python run_adversarial_suite.py
```

`evaluate_models.py` reports:

- Precision
- Recall
- F1
- ROC-AUC
- False positives
- Attack-classifier macro metrics
- Case studies
- Ablation outputs for:
  - `graph_only`
  - `graph_plus_anomaly`
  - `graph_plus_anomaly_llm`

Important:

- The third ablation mode measures explanation coverage separately.
- It does not currently change the detection threshold in the offline benchmark.
- That is intentional so the benchmark stays reproducible without live API access.

## Honest boundaries

- The bundled benchmark is synthetic, not a publication-grade enterprise dataset.
- The dashboard can use real app data if your SAIPS database contains audit, incident, and blocked-IP records.
- The LLM enhancement is optional and explanatory; the core detection logic still works without it.
- Live LLM output depends on external provider access, quota, and structured-output reliability, so demos can be run with local explanations only.
- There is currently no `verify_installation.py`, `MASTERS_PROPOSAL.md`, or other long-form proposal pack in this repo.

## Best files 

- `attack-attribution.php`
- `backend/attribution_visuals.php`
- `backend/Services/MLService.php`
- `backend/Services/AIService.php`
- `backend/api/ml_attribution.php`
- `backend/ml_service/README.md`
- `backend/ml_service/DATASET.md`
- `backend/ml_service/LIMITATIONS.md`
- `ML_RESEARCH.md`

Those show the system design, the detection path, the explainability layer, and the honesty around evaluation limits.
