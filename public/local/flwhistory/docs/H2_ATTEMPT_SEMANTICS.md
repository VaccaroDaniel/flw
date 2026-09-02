# Program 2 Gate H2 Attempt Semantics

Status: PASS

H2 preserves repeated attempts and source scores without collapsing learner history into one best/latest number.

## Identity

Each attempt stores:

| Field | Meaning |
| --- | --- |
| `sourceattemptid` | Native source attempt id, such as Moodle `quiz_attempts.id` or `flwvrroom_attempts.id` |
| `attemptno` | Source attempt number when available |
| `sourcefactkey` | Link back to the captured source event |
| `sourceeventid` | Source event row that triggered the capture |
| `lastsourceevent` | Latest source event used for this attempt snapshot |
| `normpolicyversion` | H1B frozen normalization policy version |

## Scores

Quiz attempt score fields preserve:

| Field | Meaning |
| --- | --- |
| `rawscore` | Moodle `quiz_attempts.sumgrades` |
| `maxscore` | Moodle quiz `sumgrades` |
| `scaledscore` | Bounded `rawscore / maxscore`, rounded to 5 decimals |
| `summaryjson.result` | `passed`, `failed`, or `ungraded` when source data supports it |
| `summaryjson.pass` | 1 or 0 when grade-pass can be calculated |
| `summaryjson.gradepass` | Moodle grade item pass value when configured |

FLW VR Room attempts preserve source `score`, event/source max score, normalized percent, task completion, and reliable duration.

## Repeated Attempts

Attempts are kept separate by source attempt identity and attempt number. Example:

| Attempt | Raw score | Stored separately |
| --- | --- | --- |
| 1 | 54 | Yes |
| 2 | 67 | Yes |
| 3 | 82 | Yes |

H2 does not choose best attempt, latest attempt, or official grade. Those semantics are deferred to later Program 2 gates.

## Question Attempts

For quiz attempts, H2 captures bounded question-attempt detail from Moodle question usage tables:

| Field | Source |
| --- | --- |
| `questionattemptid` | Moodle `question_attempts.id` |
| `slot` | Moodle quiz slot |
| `questionid` | Moodle question id |
| `resultstate` | Latest question step state |
| `rawmark` | `fraction * maxmark` when available |
| `maxmark` | Moodle question attempt max mark |
| `fraction` | Latest question step fraction |
| `steptime` | Latest question step time |

The H2 capture limit is bounded at 200 question attempts per source attempt.
