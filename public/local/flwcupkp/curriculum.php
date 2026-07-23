<?php
// C-UP-KP curriculum graph browser.

require_once(__DIR__ . '/../../config.php');

$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$query = optional_param('q', '', PARAM_TEXT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:viewreports', $context);

$url = new moodle_url('/local/flwcupkp/curriculum.php');
if ($frameworkid) {
    $url->param('frameworkid', $frameworkid);
}
if ($unitcode !== '') {
    $url->param('unitcode', $unitcode);
}
if ($query !== '') {
    $url->param('q', $query);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('curriculummanager', 'local_flwcupkp'));
$PAGE->set_heading(get_string('curriculummanager', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$graph = \local_flwcupkp\local\curriculum_manager::graph($frameworkid, $unitcode, $query);
$coverage = $graph['coverage'];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('curriculummanager', 'local_flwcupkp'));

if ($status !== '') {
    echo $OUTPUT->notification(get_string('curriculum' . $status, 'local_flwcupkp'), 'success');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/import_export.php', ['frameworkid' => $frameworkid]),
    get_string('importexport', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/mappings.php', ['frameworkid' => $frameworkid]),
    get_string('mappingmanager', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
foreach (\local_flwcupkp\local\curriculum_manager::entity_types() as $type => $config) {
    echo html_writer::link(new moodle_url('/local/flwcupkp/edit_entity.php', ['type' => $type]),
        get_string('add' . $config['label'], 'local_flwcupkp'), ['class' => 'btn btn-primary']);
}
echo html_writer::end_tag('div');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/flwcupkp/curriculum.php'),
    'class' => 'local-flwcupkp-filters',
]);
echo html_writer::tag('label', get_string('framework', 'local_flwcupkp') .
    html_writer::select([0 => get_string('all', 'local_flwcupkp')] +
        \local_flwcupkp\local\curriculum_manager::framework_options(), 'frameworkid', $frameworkid, false),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('unit', 'local_flwcupkp') .
    html_writer::select(['' => get_string('all', 'local_flwcupkp')] +
        \local_flwcupkp\local\curriculum_manager::unit_options(), 'unitcode', $unitcode, false),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('search') .
    html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => s($query)]),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php'), get_string('reset'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-summary']);
echo html_writer::tag('span', get_string('competencies', 'local_flwcupkp') . ': ' . (int)$coverage['competencies']);
echo html_writer::tag('span', get_string('usepoints', 'local_flwcupkp') . ': ' . (int)$coverage['use_points']);
echo html_writer::tag('span', get_string('knowledgepoints', 'local_flwcupkp') . ': ' . (int)$coverage['knowledge_points']);
echo html_writer::tag('span', get_string('compuptopology', 'local_flwcupkp') . ': ' .
    format_float((float)$coverage['competencies_linked_to_up_percent'], 1) . '%');
echo html_writer::tag('span', get_string('upkptopology', 'local_flwcupkp') . ': ' .
    format_float((float)$coverage['use_points_linked_to_kp_percent'], 1) . '%');
echo html_writer::tag('span', get_string('kpobjectcoverage', 'local_flwcupkp') . ': ' .
    format_float((float)$coverage['kps_linked_to_learning_objects_percent'], 1) . '%');
echo html_writer::end_tag('div');

if (!empty($coverage['warnings'])) {
    echo $OUTPUT->notification(implode(html_writer::empty_tag('br'), array_map('s', $coverage['warnings'])), 'warning');
}

local_flwcupkp_render_frameworks($graph['frameworks']);
local_flwcupkp_render_graph($graph);

echo $OUTPUT->footer();

/**
 * Render framework records.
 *
 * @param array $frameworks
 */
function local_flwcupkp_render_frameworks(array $frameworks): void {
    echo html_writer::tag('h3', get_string('frameworks', 'local_flwcupkp'));
    if (!$frameworks) {
        echo html_writer::tag('p', get_string('noframeworks', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
        return;
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('externalid', 'local_flwcupkp'),
        get_string('name'),
        get_string('course'),
        get_string('language'),
        get_string('status'),
        get_string('actions'),
    ];
    foreach ($frameworks as $framework) {
        $table->data[] = [
            s($framework->externalid),
            s($framework->name),
            s($framework->coursecode),
            s($framework->language),
            s($framework->status),
            html_writer::link(new moodle_url('/local/flwcupkp/edit_entity.php', [
                'type' => 'framework',
                'id' => $framework->id,
            ]), get_string('edit')),
        ];
    }
    echo html_writer::table($table);
}

/**
 * Render Competency -> UP -> KP graph.
 *
 * @param array $graph
 */
function local_flwcupkp_render_graph(array $graph): void {
    $bycomp = local_flwcupkp_group_records($graph['comp_up'], 'competencyid');
    $byup = local_flwcupkp_group_records($graph['up_kp'], 'upid');
    $objectsbytarget = local_flwcupkp_objects_by_target($graph);
    $prereqbykp = local_flwcupkp_group_records($graph['kp_prereq'], 'kpid');

    echo html_writer::tag('h3', get_string('curriculumgraph', 'local_flwcupkp'));
    if (!$graph['competencies']) {
        echo html_writer::tag('p', get_string('nographrows', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
        return;
    }

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-graph']);
    foreach ($graph['competencies'] as $competency) {
        echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-graph-section']);
        echo html_writer::tag('h4',
            html_writer::link(new moodle_url('/local/flwcupkp/edit_entity.php', ['type' => 'competency', 'id' => $competency->id]),
                s($competency->externalid)) . ' ' . s($competency->title));
        echo html_writer::tag('p', s($competency->cando ?: $competency->description), ['class' => 'local-flwcupkp-muted']);
        local_flwcupkp_render_object_chips($objectsbytarget['competency:' . $competency->id] ?? []);

        foreach ($bycomp[(int)$competency->id] ?? [] as $compup) {
            $up = $graph['use_points'][(int)$compup->upid] ?? null;
            if (!$up) {
                continue;
            }
            echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-graph-up']);
            echo html_writer::tag('div',
                html_writer::link(new moodle_url('/local/flwcupkp/edit_entity.php', ['type' => 'up', 'id' => $up->id]),
                    s($up->externalid)) . ' ' . s($up->title) .
                html_writer::tag('span', s($compup->role) . ' / ' . format_float((float)$compup->weight, 2),
                    ['class' => 'local-flwcupkp-chip']),
                ['class' => 'local-flwcupkp-graph-title']);
            local_flwcupkp_render_object_chips($objectsbytarget['up:' . $up->id] ?? []);

            foreach ($byup[(int)$up->id] ?? [] as $upkp) {
                $kp = $graph['knowledge_points'][(int)$upkp->kpid] ?? null;
                if (!$kp) {
                    continue;
                }
                $details = s($kp->domain) . ' / ' . s($upkp->role) . ' / ' . format_float((float)$upkp->weight, 2);
                echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-graph-kp']);
                echo html_writer::tag('div',
                    html_writer::link(new moodle_url('/local/flwcupkp/edit_entity.php', ['type' => 'kp', 'id' => $kp->id]),
                        s($kp->externalid)) . ' ' . s($kp->title) .
                    html_writer::tag('span', $details, ['class' => 'local-flwcupkp-chip']),
                    ['class' => 'local-flwcupkp-graph-title']);
                local_flwcupkp_render_object_chips($objectsbytarget['kp:' . $kp->id] ?? []);
                if (!empty($prereqbykp[(int)$kp->id])) {
                    $labels = [];
                    foreach ($prereqbykp[(int)$kp->id] as $prereq) {
                        if (!empty($graph['knowledge_points'][(int)$prereq->prereqkpid])) {
                            $labels[] = $graph['knowledge_points'][(int)$prereq->prereqkpid]->externalid;
                        }
                    }
                    echo html_writer::tag('div', get_string('prerequisites', 'local_flwcupkp') . ': ' .
                        s(implode(', ', $labels)), ['class' => 'local-flwcupkp-muted']);
                }
                echo html_writer::end_tag('div');
            }
            echo html_writer::end_tag('div');
        }
        echo html_writer::end_tag('section');
    }
    echo html_writer::end_tag('div');
}

/**
 * Group records by an integer property.
 */
function local_flwcupkp_group_records(array $records, string $field): array {
    $groups = [];
    foreach ($records as $record) {
        $groups[(int)$record->{$field}][] = $record;
    }
    return $groups;
}

/**
 * Build object chips keyed by target type/id.
 */
function local_flwcupkp_objects_by_target(array $graph): array {
    $out = [];
    foreach ($graph['object_map'] as $map) {
        $object = $graph['learning_objects'][(int)$map->objectid] ?? null;
        if (!$object) {
            continue;
        }
        $out[$map->targettype . ':' . (int)$map->targetid][] = $object;
    }
    return $out;
}

/**
 * Render learning-object chips.
 */
function local_flwcupkp_render_object_chips(array $objects): void {
    if (!$objects) {
        return;
    }
    $chips = [];
    foreach ($objects as $object) {
        $label = $object->unitcode . ' L' . $object->lesson . ' ' . $object->title;
        if (!empty($object->cmid)) {
            $label .= ' / CMID ' . (int)$object->cmid;
        }
        $chips[] = html_writer::link(new moodle_url('/local/flwcupkp/edit_entity.php', [
            'type' => 'object',
            'id' => $object->id,
        ]), s($label), ['class' => 'local-flwcupkp-chip']);
    }
    echo html_writer::tag('div', implode(' ', $chips), ['class' => 'local-flwcupkp-chiprow']);
}
