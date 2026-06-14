# FLW AI Assessment

Plugin component: `local_flwaiassessment`  
Version: `0.1.0-alpha`

This local Moodle plugin stores offline AI estimates for FLW speaking and writing tasks.
It is designed to work with a separate local scoring API that can call Ollama, Whisper,
or other on-premise models without sending learner data to cloud services.

## MVP Scope

- Store learner writing text and speaking transcripts.
- Store AI CEFR estimate, total score, rubric JSON, weak knowledge points, and recommendations.
- Keep teacher confirmation as the final assessment decision.
- Provide a review page at `/local/flwaiassessment/index.php`.
- Process pending records through a scheduled task when enabled in plugin settings.

## Local Scoring API Contract

The scheduled task posts JSON to:

- `POST /estimate/writing`
- `POST /estimate/speaking`

Expected response shape:

```json
{
  "cefr_level": "A2",
  "total_score": 12,
  "rubric": {
    "task_achievement": 3,
    "grammar": 2,
    "vocabulary": 2,
    "coherence": 3,
    "mechanics": 2
  },
  "weak_kps": ["past simple verb forms"],
  "recommended_lessons": ["A2 Grammar: Past simple"]
}
```

## Next Steps

1. Install the plugin from Moodle site administration.
2. Add hooks from assignment, quiz, or a future FLW speaking activity to create pending records.
3. Build the local FastAPI scoring server.
4. Connect speaking audio transcription through Whisper or Whisper.cpp.
