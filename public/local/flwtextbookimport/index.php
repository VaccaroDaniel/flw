<?php
// Review dashboard for local_flwtextbookimport.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/importer.php');

use local_flwtextbookimport\local\importer;

$defaultpath = get_config('local_flwtextbookimport', 'defaultpackagepath');
if ($defaultpath === false || trim((string)$defaultpath) === '') {
    $defaultpath = 'C:\\Users\\com\\Documents\\Estimation Speaking\\flw-moodle-importer-pilot\\output\\moodle_dry_run\\ckla_g2_u2_moodle_dry_run.json';
}

$path = optional_param('path', (string)$defaultpath, PARAM_RAW_TRIMMED);
$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$urlparams = ['path' => $path];
if ($courseid > 0) {
    $urlparams['courseid'] = $courseid;
}
$PAGE->set_url(new moodle_url('/local/flwtextbookimport/index.php', $urlparams));
$PAGE->set_context($context);
$PAGE->set_title(get_string('reviewdashboard', 'local_flwtextbookimport'));
$PAGE->set_heading(get_string('pluginname', 'local_flwtextbookimport'));

$notice = null;
$error = null;
$model = null;
$sync = null;

if ($path !== '') {
    try {
        $package = importer::load_package($path);
        $sync = importer::sync_review_rows($package);

        if (data_submitted() && confirm_sesskey()) {
            if ($action === 'publishlesson' || $action === 'unpublishlesson') {
                $sectionnumber = required_param('section', PARAM_INT);
                $result = importer::set_lesson_published($package, $sectionnumber, $action === 'publishlesson');
                $message = get_string('publishlessonresult', 'local_flwtextbookimport', (object)[
                    'section' => $sectionnumber,
                    'visible' => $result['modulesvisible'],
                    'hidden' => $result['moduleshidden'],
                ]);
                redirect(
                    new moodle_url('/local/flwtextbookimport/index.php', $urlparams),
                    $message,
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }

            $rows = optional_param_array('review', [], PARAM_RAW);
            $updated = importer::save_review_rows($rows);
            redirect(
                new moodle_url('/local/flwtextbookimport/index.php', $urlparams),
                get_string('reviewsaved', 'local_flwtextbookimport', $updated),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        $model = importer::review_model($package);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewdashboard', 'local_flwtextbookimport'));

echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-4']);
echo html_writer::start_div('form-group');
echo html_writer::label(get_string('defaultpackagepath', 'local_flwtextbookimport'), 'id_path');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'id_path',
    'name' => 'path',
    'value' => $path,
    'class' => 'form-control',
]);
echo html_writer::end_div();
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('loadpackage', 'local_flwtextbookimport'),
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('form');

if ($error !== null) {
    echo $OUTPUT->notification(s($error), 'error');
}

if ($model !== null) {
    if ($sync !== null && ((int)$sync['inserted'] > 0 || (int)$sync['updated'] > 0)) {
        echo $OUTPUT->notification(
            'Review rows synced: ' . (int)$sync['inserted'] . ' inserted, ' . (int)$sync['updated'] . ' refreshed.',
            'info'
        );
    }

    echo html_writer::start_div('mb-3');
    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $model['course']['id']]),
        get_string('opencourse', 'local_flwtextbookimport'),
        ['class' => 'btn btn-primary mr-2']
    );
    echo html_writer::link(
        new moodle_url('/local/flwcupkp/index.php'),
        get_string('flwhandoff', 'local_flwtextbookimport'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();

    $lesson1rows = array_values(array_filter($model['rows'], static function(array $row): bool {
        return (int)$row['sectionnum'] === 1 && (int)$row['cmid'] > 0;
    }));
    $lesson1visible = array_values(array_filter($lesson1rows, static function(array $row): bool {
        return !empty($row['cmvisible']);
    }));

    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo $OUTPUT->heading(get_string('previewlesson', 'local_flwtextbookimport', 1), 3);
    echo html_writer::start_div('mb-3');
    foreach ($lesson1rows as $row) {
        echo html_writer::link(
            new moodle_url('/mod/' . $row['existingmodule'] . '/view.php', ['id' => $row['cmid']]),
            s($row['name']),
            ['class' => 'btn btn-outline-secondary btn-sm mr-1 mb-1']
        );
    }
    echo html_writer::end_div();

    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-inline']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'path', 'value' => $path]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $model['course']['id']]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => 1]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'publishlesson']);
    $publishattrs = [
        'type' => 'submit',
        'value' => get_string('publishlessonconfirm', 'local_flwtextbookimport', 1),
        'class' => 'btn btn-success mr-2',
    ];
    if (count($lesson1rows) === 0) {
        $publishattrs['disabled'] = 'disabled';
    }
    echo html_writer::empty_tag('input', $publishattrs);
    echo html_writer::end_tag('form');

    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-inline']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'path', 'value' => $path]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $model['course']['id']]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => 1]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'unpublishlesson']);
    $unpublishattrs = [
        'type' => 'submit',
        'value' => get_string('unpublishlesson', 'local_flwtextbookimport', 1),
        'class' => 'btn btn-outline-danger',
    ];
    if (count($lesson1visible) === 0) {
        $unpublishattrs['disabled'] = 'disabled';
    }
    echo html_writer::empty_tag('input', $unpublishattrs);
    echo html_writer::end_tag('form');
    echo html_writer::tag('p', 'Lesson 1 visible modules: ' . count($lesson1visible) . ' / ' . count($lesson1rows),
        ['class' => 'text-muted mt-3 mb-0']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    $cards = [
        'Course' => $model['course']['shortname'] . ' (visible: ' . $model['course']['visible'] . ')',
        'Sections' => $model['counts']['sections'],
        'Plan rows' => $model['counts']['activities'],
        'Imported modules' => $model['counts']['imported'],
        'Approved rows' => $model['counts']['approved'],
        'KP metadata rows' => $model['counts']['kpready'],
    ];

    echo html_writer::start_div('row mb-4');
    foreach ($cards as $label => $value) {
        echo html_writer::start_div('col-md-2 mb-2');
        echo html_writer::start_div('card h-100');
        echo html_writer::start_div('card-body p-3');
        echo html_writer::tag('div', s((string)$label), ['class' => 'text-muted small']);
        echo html_writer::tag('strong', s((string)$value), ['class' => 'd-block']);
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    echo html_writer::end_div();

    echo $OUTPUT->heading(get_string('flwhandoff', 'local_flwtextbookimport'), 3);
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', 'Category path: ' . s($model['flw']['category_path']));
    echo html_writer::tag('li', 'Language: ' . s($model['flw']['language']));
    echo html_writer::tag('li', 'Default CEFR: ' . s($model['flw']['default_cefr']));
    echo html_writer::tag('li', 'Course hidden until teacher review: ' . ((int)$model['course']['visible'] === 0 ? 'yes' : 'no'));
    echo html_writer::tag('li', 'Ready for KP mapping handoff: ' . ($model['flw']['handoff_ready'] ? 'yes' : 'no'));
    echo html_writer::end_tag('ul');

    if (!empty($model['modulecounts'])) {
        $moduletable = new html_table();
        $moduletable->head = ['Module', 'Visible', 'Count'];
        foreach ($model['modulecounts'] as $row) {
            $moduletable->data[] = [
                s($row['module']),
                (int)$row['visible'] === 1 ? 'yes' : 'no',
                (int)$row['total'],
            ];
        }
        echo html_writer::table($moduletable);
    }

    echo html_writer::start_tag('form', ['method' => 'post']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'path', 'value' => $path]);
    if ($courseid > 0) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable table-sm';
    $table->head = [
        'OK',
        'Section',
        'Activity',
        'Module',
        'Status',
        'Source',
        'CEFR',
        'Skill',
        'KP tags',
        'Notes',
        'Moodle',
    ];

    foreach ($model['rows'] as $row) {
        $id = (int)$row['id'];
        $approvedattrs = [
            'type' => 'checkbox',
            'name' => 'review[' . $id . '][approved]',
            'value' => 1,
        ];
        if ($row['approved']) {
            $approvedattrs['checked'] = 'checked';
        }
        $approved = html_writer::empty_tag('input', $approvedattrs);
        $cefr = html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'review[' . $id . '][cefr]',
            'value' => $row['cefr'],
            'class' => 'form-control form-control-sm',
            'style' => 'min-width:4rem',
        ]);
        $skill = html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'review[' . $id . '][skill]',
            'value' => $row['skill'],
            'class' => 'form-control form-control-sm',
            'style' => 'min-width:8rem',
        ]);
        $kptags = html_writer::tag('textarea', s($row['kptags']), [
            'name' => 'review[' . $id . '][kptags]',
            'class' => 'form-control form-control-sm',
            'rows' => 2,
            'style' => 'min-width:16rem',
        ]);
        $notes = html_writer::tag('textarea', s($row['notes']), [
            'name' => 'review[' . $id . '][notes]',
            'class' => 'form-control form-control-sm',
            'rows' => 2,
            'style' => 'min-width:16rem',
        ]);
        $moodle = $row['cmid'] > 0 ?
            html_writer::link(new moodle_url('/mod/' . $row['existingmodule'] . '/view.php', ['id' => $row['cmid']]),
                'cmid ' . $row['cmid']) :
            'not imported';
        $source = trim($row['sourcepdf'] . ' ' . $row['sourcerange']);

        $table->data[] = [
            $approved,
            (int)$row['sectionnum'],
            s($row['name']),
            s($row['moodlemodule']),
            s($row['reviewstatus']),
            s($source),
            $cefr,
            $skill,
            $kptags,
            $notes,
            $moodle,
        ];
    }

    echo html_writer::table($table);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('savechanges'),
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
