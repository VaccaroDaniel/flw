# C-UP-KP Data Model

Primary tables:

- `flwcupkp_framework`: curriculum frameworks, course/language/CEFR scope, Moodle competency framework link.
- `flwcupkp_comp`: competencies and Moodle competency links.
- `flwcupkp_up`: Use Points.
- `flwcupkp_kp`: Knowledge Points.
- `flwcupkp_comp_up`: competency-to-UP mappings.
- `flwcupkp_up_kp`: UP-to-KP mappings.
- `flwcupkp_kp_prereq`: KP prerequisite graph.
- `flwcupkp_object`: lessons, activities, quiz questions, SCORM/H5P, speaking tasks, projects, resources.
- `flwcupkp_object_map`: object-to-KP/UP/competency mappings.
- `flwcupkp_evidence`: normalized evidence events.
- `flwcupkp_state`: learner KP/UP/competency state.
- `flwcupkp_rule`: versioned calculation and recommendation rules.
- `flwcupkp_recommend`: learner recommendations.
- `flwcupkp_import`: import batches and validation status.
- `flwcupkp_audit`: operational audit log.

Stable IDs are unique per entity type and must not change when titles are edited.
