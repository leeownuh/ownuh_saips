# Benchmark Dataset

The SAIPS benchmark is a synthetic, reproducible dataset for attribution experiments and dashboard evidence generation.

## Dataset files

Versioned files are stored in:

- `datasets/v1_train_events.json`
- `datasets/v1_test_cases.json`

Create or refresh these files:

```powershell
cd backend/ml_service
py -3.11 export_benchmark_version.py --version v1
```

`build_benchmark_dataset()` will load versioned files first when present, otherwise generate and save them.

## Structure

- `train_events`: historical benign and suspicious events used for model training.
- `test_cases`: labeled case windows used for evaluation, ablation, adversarial testing, and case-study export.

Each test case includes:

- `case_id`
- `label` (`1` malicious, `0` benign)
- `attack_type`
- `expected_entities`
- `description`
- `events`

## Included patterns

- Benign known-device behavior
- Benign new-device verified behavior
- Account takeover / impossible travel
- Credential stuffing
- Distributed pressure on one account
- MFA bypass abuse

## Why synthetic

- Shareable in a public portfolio
- Repeatable for grading and demos
- Fast enough to run locally
- Free of sensitive enterprise telemetry

## What it is not

- Not a production-scale SOC dataset
- Not externally validated field telemetry
- Not sufficient alone for publication-grade claims
