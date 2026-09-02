<?php
// Create or edit one C-UP-KP curriculum entity.

require_once(__DIR__ . '/../../config.php');

$type = required_param('type', PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:manageframeworks', $context);

$config = \local_flwcupkp\local\curriculum_manager::entity_config($type);
$record = \local_flwcupkp\local\curriculum_manager::get_entity($type, $id);
$editable = local_flwcupkp_entity_form_editable($type, $record);

$url = new moodle_url('/local/flwcupkp/edit_entity.php', ['type' => $type]);
if ($id) {
    $url->param('id', $id);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('edit' . $config['label'], 'local_flwcupkp'));
$PAGE->set_heading(get_string('edit' . $config['label'], 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    if (!$editable) {
        throw new invalid_parameter_exception('Published or deprecated curriculum rows must be changed through clone/deprecate workflow.');
    }
    $data = ['id' => $id];
    foreach ($config['fields'] as $field) {
        $data[$field] = optional_param($field, '', PARAM_RAW_TRIMMED);
    }
    $savedid = \local_flwcupkp\local\curriculum_manager::save_entity($type, $data);
    redirect(new moodle_url('/local/flwcupkp/entity.php', [
        'type' => $type,
        'id' => $savedid,
        'status' => 'saved',
    ]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('edit' . $config['label'], 'local_flwcupkp'));
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php'), get_string('backtocurriculum', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
if ($record) {
    echo ' ' . html_writer::link(new moodle_url('/local/flwcupkp/entity.php', [
        'type' => $type,
        'id' => $record->id,
    ]), get_string('cm1viewentity', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
}

if (!$editable) {
    echo $OUTPUT->notification(get_string('cm1semanticeditblocked', 'local_flwcupkp'), 'info');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url,
    'class' => 'local-flwcupkp-editform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

foreach ($config['fields'] as $field) {
    $value = $record->{$field} ?? local_flwcupkp_default_entity_value($type, $field);
    $label = get_string('field_' . $field, 'local_flwcupkp');
    $attributes = [
        'name' => $field,
        'id' => 'id_' . $field,
    ];
    if (in_array($field, $config['required'], true)) {
        $attributes['required'] = 'required';
    }
    if ($field === 'externalid' && $record) {
        $attributes['readonly'] = 'readonly';
    }
    if (!$editable) {
        $attributes['disabled'] = 'disabled';
    }

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formrow']);
    echo html_writer::label($label, 'id_' . $field);
    if ($field === 'frameworkid') {
        echo html_writer::select(\local_flwcupkp\local\curriculum_manager::framework_options(), $field, (int)$value, false, $attributes);
    } else if ($field === 'status' && $type !== 'object') {
        echo html_writer::select(\local_flwcupkp\local\lifecycle_governance_contract::status_options(),
            $field, (string)$value, false, $attributes);
    } else if (in_array($field, $config['textarea'], true)) {
        echo html_writer::tag('textarea', s((string)$value), $attributes + ['rows' => 4]);
    } else {
        $inputtype = in_array($field, ['courseid', 'cmid', 'moodleframeworkid', 'moodlecompetencyid'], true) ? 'number' : 'text';
        if (in_array($field, ['difficulty', 'learningload'], true)) {
            $inputtype = 'number';
            $attributes['step'] = '0.01';
        }
        echo html_writer::empty_tag('input', $attributes + ['type' => $inputtype, 'value' => s((string)$value)]);
    }
    echo html_writer::end_tag('div');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
if ($editable) {
    echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
}
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php'), get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('form');

echo $OUTPUT->footer();

/**
 * Defaults for new entity fields.
 *
 * @param string $type
 * @param string $field
 * @return string|int
 */
function local_flwcupkp_default_entity_value(string $type, string $field) {
    if ($field === 'frameworkid') {
        $options = \local_flwcupkp\local\curriculum_manager::framework_options();
        return (int)(array_key_first($options) ?: 0);
    }
    if ($field === 'status') {
        return 'draft';
    }
    if ($field === 'version') {
        return '1.0';
    }
    if ($field === 'language' && $type === 'kp') {
        return 'en';
    }
    if ($field === 'metadatajson') {
        return '{}';
    }
    if (in_array($field, ['evidencerule', 'evidencerequirements'], true)) {
        return '[]';
    }
    return '';
}

/**
 * Whether a row can be semantically edited on the direct form.
 *
 * @param string $type
 * @param \stdClass|null $record
 * @return bool
 */
function local_flwcupkp_entity_form_editable(string $type, ?\stdClass $record): bool {
    if ($record === null || $type === 'object') {
        return true;
    }
    return in_array((string)($record->status ?? 'draft'), ['draft', 'review', 'approved'], true);
}
