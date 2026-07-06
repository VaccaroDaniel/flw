# Adventure English World V2 Unit 3 Package Audit

## Build result

- Package: `adventure_english_world_v2_unit003_number_trail_moodle_package.zip`
- Student page: `index.html`
- Teacher guide: `teacher_guide.html`
- Watch video: `assets/video/Video.mp4`
- Captions: `assets/video/Video.vtt`

## Validation

| Check | Result | Detail |
|---|---|---|
| Zip integrity | PASS | `zip -T` passed |
| Video file exists | PASS | Video.mp4 |
| Video duration | PASS | 52.00 seconds |
| Captions file exists | PASS | Video.vtt |
| Image assets | PASS | 20 PNG files |
| Practice items | PASS | 108 items |
| Practice section distribution | PASS | {'L1': 12, 'L2': 12, 'L3': 12, 'L4': 12, 'L5': 12, 'L6': 12, 'L7': 12, 'W': 12, 'P': 12} |
| Try again behavior text | PASS | Included in HTML/JS |
| Correct Answer behavior text | PASS | Included in HTML/JS |
| 3 items per Practice page | PASS | JavaScript slices Practice items in groups of 3 |
| Teacher guide separate | PASS | teacher_guide.html |

## Notes

The package uses the cleaned Practice bank and includes the prior image quality-gate/repair records in `docs/` and `repair_path.csv`. Audio icons are placeholders for later audio linking.


## Revision — image fit and Watch cleanup

| Check | Result | Detail |
|---|---|---|
| Lesson/project image fit | PASS | Student image cards now use natural image height and `object-fit: contain`; no image is cropped by a fixed max-height box. |
| Watch static images | PASS | Separate student-page Watch image cards for Image 16 and Image 17 were removed. |
| Watch video | PASS | `assets/video/Video.mp4` remains inserted with captions. |
| Watch layout | PASS | Video and Video Script remain side by side on wide/medium screens and stack on small screens. |
| Practice items | PASS | 108 items retained. |

