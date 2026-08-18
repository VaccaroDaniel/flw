# local_flwexam - FLW Exam

`local_flwexam` is the FLW exam, placement, certification, and exam-session manager for Moodle. It can run FLW-native exams, connect FLW exam definitions to Moodle Quiz source courses, sync completed quiz attempts, and issue verifiable certificates when all certificate rules are satisfied.

Component: `local_flwexam`

Release: `0.1.0 alpha`

Requires: Moodle 5.1 or later

Status: alpha. Use for controlled exam pilots and internal validation before official certification use.

## What This Plugin Does

- Defines FLW placement and exam records by language, track, level, and skill.
- Supports two delivery modes:
  - Moodle Quiz-backed exams.
  - Internal FLW question delivery.
- Lets learners take available exams and view their results.
- Lets teachers/managers run self, teacher, and official exam sessions.
- Syncs submitted Moodle Quiz attempts into FLW exam results.
- Applies certificate gates before creating a certificate.
- Provides public certificate verification by verification code.
- Exposes selected result and certificate operations through Moodle web services.

## Main Pages

| Page | Purpose |
| --- | --- |
| `/local/flwexam/index.php` | Learner exam center, available exams, and history. |
| `/local/flwexam/take.php` | Available exam list. |
| `/local/flwexam/attempt.php?examid=EXAMID` | Starts an internal exam or redirects to the linked Moodle Quiz. |
| `/local/flwexam/result.php?id=RESULTID` | Detailed result view. |
| `/local/flwexam/verify.php?code=VERIFYCODE` | Public certificate verification. |
| `/local/flwexam/manage.php` | Exam management for authorized staff. |
| `/local/flwexam/questions.php?examid=EXAMID` | Internal question editor. |
| `/local/flwexam/sessions.php` | Self, teacher, and official exam sessions. |

## User Workflows

### Learner

1. Open the FLW Exam center.
2. Choose an available placement test or exam.
3. Complete the Moodle Quiz or internal FLW exam.
4. Open the result page.
5. If certificate gates pass, use the verification code or certificate link.

### Teacher

1. Open exam sessions.
2. Create or manage self/teacher exam sessions.
3. Give learners the access path or access code.
4. Review submitted results.
5. Use results to guide placement, remediation, or certificate readiness.

### Admin/manager

1. Seed exam definitions and question sets.
2. Build or link Moodle Quiz source courses.
3. Configure official sessions and visibility.
4. Review certificate rules and revocation authority.
5. Validate public verification output.

## Exam Delivery Modes

### Moodle Quiz-backed

The FLW exam definition points learners to a Moodle Quiz. Finished quiz attempts are observed and synced back into FLW exam result records.

Use this mode when you need Moodle question bank behavior, random slots, timing, security controls, and standard Moodle attempt review.

### Internal FLW questions

The plugin can also use its own internal question records. This is useful for lightweight pilots, direct JSON import, and fast testing without a full Moodle question bank.

## CLI Tools

Run commands from the Moodle root.

### Build quiz source courses

```bash
php local/flwexam/cli/build_quiz_source_courses.php --language=en --only=all
```

Useful options:

| Option | Meaning |
| --- | --- |
| `--questions=N` | Number of generated source questions. Default: `1008`; must be over `1000`. |
| `--random-slots=N` | Random slots for exams. Default: `20`. |
| `--placement-random-slots=N` | Random slots for placement quizzes. Default: `30`. |
| `--trim-random-slots` | Rebuild random slots to the requested count. |
| `--language=CODE` | Language code: `en`, `ru`, `zh`, `de`, `ja`, `fr`, `es`. |
| `--only=TYPE` | `all`, `placement`, or `exam`. |

### Seed FLW exams

```bash
php local/flwexam/cli/seed_all_exams.php --replace
```

Useful options:

| Option | Meaning |
| --- | --- |
| `--replace` or `-r` | Replace existing seeded exam definitions. |
| `--help` | Show CLI help. |

## Quiz Attempt Sync

The plugin observes Moodle Quiz events:

- Attempt submitted.
- Attempt graded.
- Manual grading completed.

When a visible FLW exam is linked to the quiz, the observer records the quiz attempt through `record_quiz_attempt_result_from_event`.

If results are missing:

1. Confirm the FLW exam is visible and linked to the correct quiz.
2. Confirm the quiz attempt is finished and graded where required.
3. Confirm the observer is enabled in Moodle events.
4. Re-run any relevant repair/sync workflow if one has been added in the local site.

## Certificate Gates

A certificate should only be issued when the configured gates are satisfied. The current implementation checks certificate readiness using:

- Overall threshold.
- Skill-floor thresholds.
- Critical KP results.
- Moderation status.
- Integrity status.
- Existing valid certificate for the same learner, exam, language, track, and level.

Certificate verification exposes safe public fields only. Do not expose private attempt details on the public verification page.

## Exam Sessions

Sessions can represent:

- Self exam.
- Teacher exam session.
- Official exam session.

Session configuration can include:

- Access code.
- Proctor.
- Group restriction.
- Maximum attempts.
- Start/end availability window.
- Visibility/status such as draft, open, or closed.

Use official sessions for high-stakes certification. Use self/teacher sessions for diagnostics and class practice.

## Web Services

Service name: `FLW Exam services`

Short name: `flwexam`

Default state: disabled and restricted users enabled.

Functions:

| Function | Purpose |
| --- | --- |
| `local_flwexam_get_my_history` | Return the current user's exam history. |
| `local_flwexam_get_result` | Return a specific result the caller may view. |
| `local_flwexam_verify_certificate` | Verify a certificate code. |
| `local_flwexam_submit_result` | Submit result data for authorized callers. |

Enable the service only for trusted integrations and assign tokens carefully.

## Capabilities

| Capability | Purpose | Default roles |
| --- | --- | --- |
| `local/flwexam:viewown` | View own exam center/history/result | User, student, teacher, editing teacher, manager |
| `local/flwexam:viewall` | View all results | Teacher, editing teacher, manager |
| `local/flwexam:submitresult` | Submit result records | Editing teacher, manager |
| `local/flwexam:manageexams` | Manage exam definitions | Manager |
| `local/flwexam:manageselfexams` | Manage self exam flows | Teacher, editing teacher, manager |
| `local/flwexam:manageteacherexams` | Manage teacher sessions | Teacher, editing teacher, manager |
| `local/flwexam:manageofficialexams` | Manage official sessions | Manager |
| `local/flwexam:verifycertificate` | Verify certificates | Authenticated users and staff |
| `local/flwexam:revokecertificate` | Revoke certificates | Manager |

## Database

Important table groups:

| Table | Purpose |
| --- | --- |
| `local_flwexam_exams` | Exam definitions and delivery settings. |
| `local_flwexam_results` | Learner result records. |
| `local_flwexam_certificates` | Issued certificate records and verification codes. |
| `local_flwexam_questions` | Internal FLW question records. |
| `local_flwexam_sessions` | Self, teacher, and official exam sessions. |

Exact fields can vary by migration. Check `db/install.xml` and `db/upgrade.php` before writing direct SQL or import tooling.

## Integration Points

- Moodle Quiz: source delivery and attempt sync.
- `local_flwplacement`: placement recommendations can use exam/placement result history.
- `local_flwcupkp`: exam results and critical KP outcomes can become evidence for C-UP-KP state.
- `theme_flwacademy`: learner-facing exam links and clean navigation.

## Testing Checklist

1. Seed exams in a non-production course.
2. Build quiz source courses for one language.
3. Confirm generated quizzes have random slots and source questions.
4. Log in as a learner and complete one placement quiz.
5. Confirm the FLW result is created after quiz submission/grading.
6. Open the learner result page.
7. Open staff result view.
8. Trigger a certificate-ready result and verify the public code.
9. Confirm a duplicate valid certificate is not issued for the same scope.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Learner cannot see exams | Exam visibility, session window, role capability, and language/track filters. |
| Attempt redirects to the wrong quiz | Linked quiz ID on the exam definition. |
| Finished quiz does not create a result | Quiz event observer, quiz grading status, exam visibility, and linked quiz ID. |
| Certificate is not issued | Overall threshold, skill floor, critical KP, moderation, integrity, and duplicate certificate checks. |
| Public verification fails | Verification code, certificate revocation state, and safe public route access. |

## Production Notes

Exam and certificate records can affect learner placement and credentials. Before production use, validate exam forms, proctoring policy, data retention, certificate revocation process, and token access for web services.
