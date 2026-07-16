# FLW Exam local plugin

`local_flwexam` is the official FLW Exam module for Moodle. It is intentionally separate from FLW Placement.

- Placement Test: estimates the learner's current level and recommends the next FLW learning path.
- Exam: evaluates whether the learner has earned an official FLW/CEFR certificate for a specific language, FLW track, and CEFR level.

## Database tables

The plugin stores official exam data in independent `local_flwexam_*` tables:

- `local_flwexam_exams`: exam definitions and certificate rule profiles.
- `local_flwexam_attempts`: submitted attempt metadata.
- `local_flwexam_results`: final exam result records.
- `local_flwexam_skill_scores`: skill-level scores for each result.
- `local_flwexam_kp_results`: knowledge-point gate results.
- `local_flwexam_certificates`: issued certificate records.
- `local_flwexam_verify_tokens`: public verification codes.
- `local_flwexam_api_clients`: optional API client policy records.
- `local_flwexam_audit_log`: security and API audit events.

Exam results are not stored in course completion tables.

## Certificate rule

A certificate is issued only when all gates pass:

- overall score is greater than or equal to the exam required threshold;
- every submitted skill score is greater than or equal to the required skill floor;
- all critical KP gates in the exam profile pass;
- moderation is approved when the profile requires moderation;
- integrity status is `clear`.

The installer seeds one example profile: `EN-RW-A1-CERT` for English Real World A1.

## Capabilities

- `local/flwexam:viewown`
- `local/flwexam:viewall`
- `local/flwexam:submitresult`
- `local/flwexam:manageexams`
- `local/flwexam:verifycertificate`
- `local/flwexam:revokecertificate`

Learners can view only their own result records. Teachers/managers need `local/flwexam:viewall` to view other learners' results. Only users with `local/flwexam:submitresult` can submit official results.

## Pages

- `/local/flwexam/index.php`: logged-in learner exam history.
- `/local/flwexam/take.php`: available exams the learner can start anytime.
- `/local/flwexam/attempt.php?examid=EXAM_ID`: server-side graded learner attempt.
- `/local/flwexam/result.php?id=RESULT_ID`: result details, protected by ownership or `viewall`.
- `/local/flwexam/verify.php?code=VERIFY_CODE`: public certificate verification with safe fields only.

## External API functions

Enable the Moodle web-service named `FLW Exam services`, assign permitted users, and generate Moodle tokens as usual.

Functions:

- `local_flwexam_get_my_history`
- `local_flwexam_get_result`
- `local_flwexam_verify_certificate`
- `local_flwexam_submit_result`

Example `local_flwexam_submit_result` payload:

```json
{
  "userid": 5,
  "examid": 1,
  "language": "en",
  "learning_course_category": "real_world",
  "cefr_level": "A1",
  "overall_score": 82,
  "skill_scores": [
    {"skill": "listening", "score": 80},
    {"skill": "speaking", "score": 78},
    {"skill": "reading", "score": 90},
    {"skill": "writing", "score": 76}
  ],
  "kp_results": [
    {"kpcode": "a1_greetings", "score": 85, "passed": true, "critical": true},
    {"kpcode": "a1_personal_information", "score": 80, "passed": true, "critical": true},
    {"kpcode": "a1_everyday_transactions", "score": 75, "passed": true, "critical": true}
  ],
  "integrity_status": "clear",
  "moderation_status": "approved",
  "attempt_metadata_json": "{\"source\":\"flw_exam_engine\",\"external_attempt_id\":\"demo-001\"}"
}
```

Example response excerpt:

```json
{
  "id": 12,
  "pass_status": "passed",
  "certificate_status": "issued",
  "certificate_id": 3,
  "verify_code": "FLW-VERIFY-..."
}
```

Example verification response:

```json
{
  "valid": true,
  "certificate_code": "FLW-CERT-...",
  "learner_name": "Ada L.",
  "language": "en",
  "learning_course_category": "real_world",
  "cefr_level": "A1",
  "status": "valid"
}
```

## Installation

1. Copy the plugin to `public/local/flwexam`.
2. Run Moodle upgrade:

```powershell
C:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe C:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\admin\cli\upgrade.php
```

3. Purge caches:

```powershell
C:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe C:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\admin\cli\purge_caches.php
```

4. Review roles/capabilities and enable the web service only for trusted assessment users.

## Manual validation checklist

- Run PHP lint on plugin PHP files.
- Confirm `db/install.xml` is well-formed.
- Run Moodle upgrade without XMLDB errors.
- Confirm the seeded `EN-RW-A1-CERT` exam definition exists.
- Confirm `/local/flwexam/take.php` shows the seeded English Real World A1 exam.
- Complete `/local/flwexam/attempt.php?examid=1` as a learner and confirm the result appears in history.
- Submit a failing result and confirm no certificate is issued.
- Submit a passing result with all critical KP gates passed and moderation approved; confirm certificate and verification token are created.
- Submit a second passing result for the same learner/exam/language/track/level; confirm no duplicate valid certificate is created.
- Log in as the learner and confirm `/local/flwexam/index.php` shows only their history.
- Try another learner's `/local/flwexam/result.php?id=...` without `viewall`; confirm access is denied.
- Open `/local/flwexam/verify.php?code=...` logged out; confirm only certificate-safe fields are shown.
- Confirm audit rows are written for submission and verification.
