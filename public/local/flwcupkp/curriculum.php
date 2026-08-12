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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    require_capability('local/flwcupkp:manageframeworks', $context);

    $action = required_param('action', PARAM_ALPHANUMEXT);
    if ($action === 'bulkstatus') {
        $type = required_param('bulkstatustype', PARAM_ALPHANUMEXT);
        $scopeframeworkid = optional_param('bulkframeworkid', $frameworkid, PARAM_INT);
        $newstatus = required_param('newstatus', PARAM_ALPHANUMEXT);
        \local_flwcupkp\local\curriculum_manager::bulk_update_status($type, $scopeframeworkid, $newstatus);
        redirect(new moodle_url('/local/flwcupkp/curriculum.php', [
            'frameworkid' => $scopeframeworkid,
            'status' => 'bulkstatusupdated',
        ]));
    }

    if ($action === 'cloneversion') {
        $sourceframeworkid = required_param('cloneframeworkid', PARAM_INT);
        $newversion = required_param('cloneversion', PARAM_TEXT);
        $suffix = required_param('clonesuffix', PARAM_ALPHANUMEXT);
        $result = \local_flwcupkp\local\curriculum_manager::clone_framework_version(
            $sourceframeworkid,
            $newversion,
            $suffix
        );
        redirect(new moodle_url('/local/flwcupkp/curriculum.php', [
            'frameworkid' => $result['frameworkid'],
            'status' => 'versioncloned',
        ]));
    }

    throw new invalid_parameter_exception('Unknown C-UP-KP curriculum action.');
}

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
echo html_writer::link(new moodle_url('/local/flwcupkp/trace.php', ['frameworkid' => $frameworkid, 'unitcode' => $unitcode]),
    get_string('traceabilityreport', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
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

if (has_capability('local/flwcupkp:manageframeworks', $context)) {
    local_flwcupkp_render_bulk_tools($frameworkid);
}

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

local_flwcupkp_render_visual_graph($graph);
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
 * Render controlled bulk curriculum operations.
 *
 * @param int $frameworkid
 */
function local_flwcupkp_render_bulk_tools(int $frameworkid): void {
    $frameworks = \local_flwcupkp\local\curriculum_manager::framework_options();

    echo html_writer::tag('h3', get_string('bulkoperations', 'local_flwcupkp'));
    if (!$frameworks) {
        echo html_writer::tag('p', get_string('noframeworks', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
        return;
    }

    $selectedframework = $frameworkid > 0 ? $frameworkid : (int)array_key_first($frameworks);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-bulktools']);

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/flwcupkp/curriculum.php', ['frameworkid' => $frameworkid]),
        'class' => 'local-flwcupkp-inlineform',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'bulkstatus']);
    echo html_writer::tag('strong', get_string('bulkstatuschange', 'local_flwcupkp'));
    echo html_writer::tag('label', get_string('framework', 'local_flwcupkp') .
        html_writer::select($frameworks, 'bulkframeworkid', $selectedframework, false));
    echo html_writer::tag('label', get_string('entitytype', 'local_flwcupkp') . html_writer::select([
        'competency' => get_string('competencies', 'local_flwcupkp'),
        'up' => get_string('usepoints', 'local_flwcupkp'),
        'kp' => get_string('knowledgepoints', 'local_flwcupkp'),
        'framework' => get_string('frameworks', 'local_flwcupkp'),
    ], 'bulkstatustype', 'competency', false));
    echo html_writer::tag('label', get_string('newstatus', 'local_flwcupkp') . html_writer::select([
        'draft' => 'draft',
        'review' => 'review',
        'validated' => 'validated',
        'active' => 'active',
        'archived' => 'archived',
    ], 'newstatus', 'review', false));
    echo html_writer::tag('button', get_string('applybulkstatus', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-secondary',
    ]);
    echo html_writer::end_tag('form');

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/flwcupkp/curriculum.php', ['frameworkid' => $frameworkid]),
        'class' => 'local-flwcupkp-inlineform',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'cloneversion']);
    echo html_writer::tag('strong', get_string('versionclone', 'local_flwcupkp'));
    echo html_writer::tag('label', get_string('sourceframework', 'local_flwcupkp') .
        html_writer::select($frameworks, 'cloneframeworkid', $selectedframework, false));
    echo html_writer::tag('label', get_string('newversion', 'local_flwcupkp') . html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'cloneversion',
        'value' => '1.1',
        'maxlength' => 40,
    ]));
    echo html_writer::tag('label', get_string('externalidsuffix', 'local_flwcupkp') . html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'clonesuffix',
        'value' => 'v11',
        'maxlength' => 32,
    ]));
    echo html_writer::tag('button', get_string('cloneframeworkversion', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-secondary',
    ]);
    echo html_writer::end_tag('form');

    echo html_writer::end_tag('div');
}

/**
 * Render a compact many-to-many relationship graph.
 *
 * @param array $graph
 */
function local_flwcupkp_render_visual_graph(array $graph): void {
    $bycomp = local_flwcupkp_group_records($graph['comp_up'], 'competencyid');
    $byup = local_flwcupkp_group_records($graph['up_kp'], 'upid');
    $objectsbytarget = local_flwcupkp_objects_by_target($graph);
    $rows = [];

    foreach ($graph['competencies'] as $competency) {
        $ups = [];
        $kps = [];
        $objects = $objectsbytarget['competency:' . (int)$competency->id] ?? [];
        foreach ($bycomp[(int)$competency->id] ?? [] as $compup) {
            $up = $graph['use_points'][(int)$compup->upid] ?? null;
            if (!$up) {
                continue;
            }
            $ups[(int)$up->id] = $up;
            foreach ($objectsbytarget['up:' . (int)$up->id] ?? [] as $object) {
                $objects[(int)$object->id] = $object;
            }
            foreach ($byup[(int)$up->id] ?? [] as $upkp) {
                $kp = $graph['knowledge_points'][(int)$upkp->kpid] ?? null;
                if (!$kp) {
                    continue;
                }
                $kps[(int)$kp->id] = $kp;
                foreach ($objectsbytarget['kp:' . (int)$kp->id] ?? [] as $object) {
                    $objects[(int)$object->id] = $object;
                }
            }
        }
        $rows[] = [$competency, $ups, $kps, $objects];
    }

    echo html_writer::tag('h3', get_string('relationshipview', 'local_flwcupkp'));
    if (!$rows) {
        echo html_writer::tag('p', get_string('nographrows', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
        return;
    }

    $visible = array_slice($rows, 0, 24);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-visual-graph']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-visual-head']);
    foreach (['competency', 'usepoint', 'knowledgepoint', 'activities'] as $label) {
        echo html_writer::tag('strong', get_string($label, $label === 'activities' ? 'moodle' : 'local_flwcupkp'));
    }
    echo html_writer::end_tag('div');

    foreach ($visible as [$competency, $ups, $kps, $objects]) {
        echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-visual-row']);
        echo local_flwcupkp_visual_node('competency', $competency);
        echo html_writer::span('-&gt;', 'local-flwcupkp-visual-edge');
        echo local_flwcupkp_visual_node_list('up', $ups);
        echo html_writer::span('-&gt;', 'local-flwcupkp-visual-edge');
        echo local_flwcupkp_visual_node_list('kp', $kps);
        echo html_writer::span('-&gt;', 'local-flwcupkp-visual-edge');
        echo local_flwcupkp_visual_node_list('object', $objects);
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');

    if (count($rows) > count($visible)) {
        echo html_writer::tag('p', get_string('showinggraphpaths', 'local_flwcupkp',
            (object)['shown' => count($visible), 'total' => count($rows)]), ['class' => 'local-flwcupkp-muted']);
    }
}

/**
 * Render a group of graph nodes.
 *
 * @param string $type
 * @param array $records
 * @return string
 */
function local_flwcupkp_visual_node_list(string $type, array $records): string {
    if (!$records) {
        return html_writer::tag('div', get_string('none'), ['class' => 'local-flwcupkp-visual-nodes']);
    }

    $nodes = [];
    $visible = array_slice($records, 0, 8, true);
    foreach ($visible as $record) {
        $nodes[] = local_flwcupkp_visual_node($type, $record);
    }
    if (count($records) > count($visible)) {
        $nodes[] = html_writer::tag('span', '+' . (count($records) - count($visible)),
            ['class' => 'local-flwcupkp-visual-more']);
    }

    return html_writer::tag('div', implode('', $nodes), ['class' => 'local-flwcupkp-visual-nodes']);
}

/**
 * Render one graph node.
 *
 * @param string $type
 * @param \stdClass $record
 * @return string
 */
function local_flwcupkp_visual_node(string $type, \stdClass $record): string {
    $label = $record->externalid ?? $record->title ?? $record->name ?? '';
    $title = $record->title ?? $record->name ?? '';
    $params = ['type' => $type, 'id' => (int)$record->id];
    $link = html_writer::link(new moodle_url('/local/flwcupkp/edit_entity.php', $params), s($label));
    if ($title !== '' && $title !== $label) {
        $link .= html_writer::tag('small', s($title));
    }
    return html_writer::tag('span', $link, ['class' => 'local-flwcupkp-visual-node local-flwcupkp-visual-' . $type]);
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
