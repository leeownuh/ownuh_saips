# Limitations

Updated: April 10, 2026

## Data limits

- The benchmark dataset is synthetic and intentionally small.
- Some labels are derived from scenario construction rather than analyst-reviewed incident labels.
- Real enterprise drift, seasonality, and user heterogeneity are underrepresented.

## Model limits

- The anomaly detector is still feature-engineered and lightweight.
- The attack classifier depends on behaviour patterns already visible in the event stream.
- The graph stage is relationship-based, not a learned graph neural network.

## Product limits

- The web app can fall back to heuristic scoring when Python or trained models are unavailable.
- The bundled evaluation is case-level and should be treated as portfolio evidence, not publication-grade proof on its own.
- The `graph_plus_anomaly_llm` benchmark mode evaluates explanation coverage separately; it does not currently change the detection threshold.
- Live LLM narratives and executive reports depend on external provider access, available quota, and structured-output reliability.
- When that provider layer is unavailable, the product degrades to deterministic local summaries instead of blocking the workflow.
- Time-window and cross-environment evaluations are implemented, but they still run on the same small synthetic benchmark family.

## Research next steps

- Replace synthetic labels with analyst-verified case labels.
- Extend temporal validation over longer windows and higher event volume.
- Add feature ablations per model, not just pipeline-level ablations.
- Add calibration checks and analyst feedback loops.
- Add attack-type error analysis and ranking-quality metrics (top-k triage performance).
