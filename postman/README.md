# Danesh Online Exam – Postman Regression Suite

This folder contains a Postman collection and environment for the `danesh-online-exam` WordPress plugin.

## Files
- `danesh_regression_collection.json` – regression scenarios with auto-setup and anti-leak checks.
- `danesh_regression_environment.example.json` – example environment with required variables.

## Setup
1. Import both files into Postman (or run with Newman).
2. Duplicate the environment and fill in:
   - `base` (e.g., `https://dfpgroup.ir/wp-json`)
   - `admin_user`, `admin_app_password`
   - `studentA_user`, `studentA_app_password`
   - `studentB_user`, `studentB_app_password`
3. Optional: set `use_existing_setup` to `1` and provide `existing_exam_id` / `existing_question_id` to reuse data instead of auto-creating.

## Running
- Ensure the environment is selected.
- Run the full collection. The Admin folder creates/publishes an exam (unless reuse is enabled), Student A answers, and Student B verification ensures no data leakage.
- Runtime variables (`exam_id`, `question_id`, `choice_id_A`, `attempt_id_A`, `attempt_id_B`) are captured automatically during the run.

## Notes
- All requests use Basic Auth (per folder) and `danesh_envelope=1` query for consistent responses.
- Requests send `Accept: application/json`; bodies use `Content-Type: application/json` where applicable.
