<?php
// Upgrade steps for local_flwcupkp.

defined('MOODLE_INTERNAL') || die();

function xmldb_local_flwcupkp_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026071700) {
        // Initial install schema is handled by install.xml.
        upgrade_plugin_savepoint(true, 2026071700, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026072300) {
        $table = new xmldb_table('flwcupkp_calsnapshot');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '120', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('unitcode', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('targettype', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('summaryjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('reportjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('checksum', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('scope_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'unitcode', 'targettype']);
            $table->add_index('time_ix', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $table->add_index('checksum_ix', XMLDB_INDEX_NOTUNIQUE, ['checksum']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026072300, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026072301) {
        $table = new xmldb_table('flwcupkp_calproposal');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('snapshotid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('targettype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '120', null, XMLDB_NOTNULL, null, null);
            $table->add_field('version', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, null, null, 'draft');
            $table->add_field('thresholdsjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('previewjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('activatedruleid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('snapshot_ix', XMLDB_INDEX_NOTUNIQUE, ['snapshotid']);
            $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('version_ix', XMLDB_INDEX_NOTUNIQUE, ['version']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026072301, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026080700) {
        $table = new xmldb_table('flwcupkp_calrecalc');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('proposalid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'queued');
            $table->add_field('mode', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'immediate');
            $table->add_field('candidate_total', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('changed_or_created', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('applied', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('skipped', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('simulationjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('resultjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('errorsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('proposal_ix', XMLDB_INDEX_NOTUNIQUE, ['proposalid']);
            $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('time_ix', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026080700, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026081300) {
        $table = new xmldb_table('flwcupkp_eval_period');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('frameworkid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '120', null, XMLDB_NOTNULL, null, null);
            $table->add_field('periodtype', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'unit');
            $table->add_field('datestart', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('dateend', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('cefr', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('unitcode', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('configjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, null, null, 'active');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('scope_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'frameworkid', 'unitcode']);
            $table->add_index('type_ix', XMLDB_INDEX_NOTUNIQUE, ['periodtype', 'status']);
            $table->add_index('date_ix', XMLDB_INDEX_NOTUNIQUE, ['datestart', 'dateend']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('flwcupkp_eval_snapshot');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('frameworkid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('periodid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('evaluationtype', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'unit');
            $table->add_field('cefrinterpretation', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('masteryruleversion', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('evaluationruleversion', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'eval-v1');
            $table->add_field('frameworkversion', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('evidencecutoff', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('snapshotversion', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, null, null, 'current');
            $table->add_field('summaryjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('stateidsjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('evidenceidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('diagnosticsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('recommendationsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('checksum', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('useridcreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('learner_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('period_ix', XMLDB_INDEX_NOTUNIQUE, ['periodid', 'evaluationtype']);
            $table->add_index('cutoff_ix', XMLDB_INDEX_NOTUNIQUE, ['evidencecutoff']);
            $table->add_index('checksum_ix', XMLDB_INDEX_NOTUNIQUE, ['checksum']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('flwcupkp_selfeval');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('periodid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('targettype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('targetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('selfrating', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('reflection', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('provenance', XMLDB_TYPE_CHAR, '80', null, null, null, 'learner');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('learner_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('target_ix', XMLDB_INDEX_NOTUNIQUE, ['targettype', 'targetid']);
            $table->add_index('period_ix', XMLDB_INDEX_NOTUNIQUE, ['periodid']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('flwcupkp_diagnostic');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('periodid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('targettype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('targetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('gapcategory', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, null);
            $table->add_field('diagnosticreason', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('stateidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('evidenceidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('confidence', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, 0);
            $table->add_field('ruleversion', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'diagnostic-v1');
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, null, null, 'active');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('learner_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('target_ix', XMLDB_INDEX_NOTUNIQUE, ['targettype', 'targetid']);
            $table->add_index('category_ix', XMLDB_INDEX_NOTUNIQUE, ['gapcategory', 'status']);
            $table->add_index('period_ix', XMLDB_INDEX_NOTUNIQUE, ['periodid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026081300, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082801) {
        // Program 3 Gate A0: code/doc checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082801, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082802) {
        // Program 3 Gate C0: repository audit checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082802, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082803) {
        // Program 3 Gate C1: canonical domain model checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082803, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082804) {
        // Program 3 Gate C1B: ontology boundary checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082804, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082805) {
        // Program 3 Gate C2: relationship graph contract checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082805, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082806) {
        // Program 3 Gate C3: content/evidence mapping contract checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082806, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082807) {
        // Program 3 Gate C3B: evidence semantics and quality contract checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082807, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082808) {
        // Program 3 Gate C4: lifecycle/versioning/governance checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082808, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082809) {
        // Program 3 Gate C5: Foundation V1 freeze checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082809, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082900) {
        // Program 3 Gate C5B: Foundation Inspector checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082900, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082901) {
        // Program 3 Gate CM1: Core Curriculum Manager checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082901, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082902) {
        // Program 3 Gate CM2: Relationship Editor + Where Used checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082902, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082903) {
        // Program 3 Gate CM3: Coverage + Bulk Management + Governance UI checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082903, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082904) {
        // Program 3 Gate CM4: Management V1 freeze checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082904, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082905) {
        // Program 3 Gate E1: History V1 evidence adapter checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026082905, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082906) {
        // Program 3 Gate E2: current mastery/confidence state cache metadata.
        $table = new xmldb_table('flwcupkp_state');

        $field = new xmldb_field('policyversion', XMLDB_TYPE_CHAR, '80', null, null, null, null, 'ruleversion');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('trend', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'policyversion');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('evidencehash', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'trend');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('evidenceidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'evidencehash');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('calculatedtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'evidenceidsjson');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082906, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026082907) {
        // Program 3 Gate E3: retention/retrieval/review state cache metadata.
        $table = new xmldb_table('flwcupkp_state');

        $field = new xmldb_field('retentionstate', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'calculatedtime');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('retentionconfidence', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, '0',
            'retentionstate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('retentionnextreview', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'retentionconfidence');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('retentionlastretrieval', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'retentionnextreview');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('retentionretrievalcount', XMLDB_TYPE_INTEGER, '10', null, null, null, '0',
            'retentionlastretrieval');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('retentionpolicyversion', XMLDB_TYPE_CHAR, '80', null, null, null, null,
            'retentionretrievalcount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('retentionevidencehash', XMLDB_TYPE_CHAR, '64', null, null, null, null,
            'retentionpolicyversion');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('retentionevidenceidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null,
            'retentionevidencehash');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('retentioncalculatedtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'retentionevidenceidsjson');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('retention_ix', XMLDB_INDEX_NOTUNIQUE, ['retentionstate', 'retentionnextreview']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026082907, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083000) {
        // Program 3 Gate A1: competency-centered learner goal and immutable versions.
        $table = new xmldb_table('flwcupkp_goal');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('frameworkid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('unitcode', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('desiredprofilejson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('competencyidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('upidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('kpidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('cefr', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('flwstage', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $table->add_field('purpose', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('priorityskillsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('targetdate', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('weeklytarget', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, '0');
            $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'STUDENT');
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'active');
            $table->add_field('currentversion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('activeversionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('goalpolicyversion', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $table->add_field('checksum', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('useridcreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('learner_scope_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'unitcode']);
            $table->add_index('framework_ix', XMLDB_INDEX_NOTUNIQUE, ['frameworkid']);
            $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status', 'source']);
            $table->add_index('targetdate_ix', XMLDB_INDEX_NOTUNIQUE, ['targetdate']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('flwcupkp_goal_version');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('goalid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('frameworkid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('unitcode', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('desiredprofilejson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('competencyidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('upidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('kpidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('cefr', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('flwstage', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $table->add_field('purpose', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('priorityskillsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('targetdate', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('weeklytarget', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, '0');
            $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'STUDENT');
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'active');
            $table->add_field('goalpolicyversion', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $table->add_field('checksum', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('changecomment', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('useridcreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('goal_version_uix', XMLDB_INDEX_UNIQUE, ['goalid', 'version']);
            $table->add_index('learner_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('scope_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'frameworkid', 'unitcode']);
            $table->add_index('checksum_ix', XMLDB_INDEX_NOTUNIQUE, ['checksum']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026083000, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083001) {
        // Program 3 Gate A2: placement diagnostic and cold-start state cache.
        $table = new xmldb_table('flwcupkp_placement_state');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('frameworkid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('unitcode', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('sourcekey', XMLDB_TYPE_CHAR, '191', null, null, null, null);
            $table->add_field('sourcefactkey', XMLDB_TYPE_CHAR, '191', null, XMLDB_NOTNULL, null, null);
            $table->add_field('placementstatus', XMLDB_TYPE_CHAR, '40', null, null, null, '');
            $table->add_field('policystate', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sourcecategory', XMLDB_TYPE_CHAR, '60', null, null, null, null);
            $table->add_field('previouslevel', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('currentlevel', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('score', XMLDB_TYPE_NUMBER, '12, 5', null, null, null, null);
            $table->add_field('confidence', XMLDB_TYPE_NUMBER, '12, 5', null, null, null, null);
            $table->add_field('placementtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('staleafter', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('assesseddimensionsjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('evidenceidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('diagnosticjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('policyversion', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, null);
            $table->add_field('checksum', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('sourcefact_ix', XMLDB_INDEX_NOTUNIQUE, ['sourcefactkey']);
            $table->add_index('learner_scope_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'unitcode']);
            $table->add_index('framework_ix', XMLDB_INDEX_NOTUNIQUE, ['frameworkid']);
            $table->add_index('state_ix', XMLDB_INDEX_NOTUNIQUE, ['policystate', 'sourcecategory']);
            $table->add_index('placementtime_ix', XMLDB_INDEX_NOTUNIQUE, ['placementtime']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026083001, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083002) {
        // Program 3 Gate A3: adaptive decision policy V1 code/API checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026083002, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083003) {
        // Program 3 Gate A4: goal-gap initial path V1 code/API checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026083003, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083004) {
        // Program 3 Gate A4B: candidate eligibility/activity resolution checkpoint only; no schema changes.
        upgrade_plugin_savepoint(true, 2026083004, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083005) {
        // Program 3 Gate A5: scoped adaptive-path recommendation metadata.
        $table = new xmldb_table('flwcupkp_recommend');

        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('unitcode', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'courseid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'objectid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('recommendationtype', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'targetid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('policyversion', XMLDB_TYPE_CHAR, '80', null, null, null, null,
            'recommendationtype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('sourcehash', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'policyversion');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('decisioncode', XMLDB_TYPE_CHAR, '60', null, null, null, null, 'sourcehash');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('learner_scope_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'unitcode']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('a5_hash_ix', XMLDB_INDEX_NOTUNIQUE, ['policyversion', 'sourcehash']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('a5_status_ix', XMLDB_INDEX_NOTUNIQUE, ['status', 'policyversion']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('cm_ix', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026083005, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083006) {
        // Program 3 Gate A5B: read-only trajectory simulation and invariant testing checkpoint.
        upgrade_plugin_savepoint(true, 2026083006, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083007) {
        // Program 3 Gate A5C: read-only progress and goal-readiness semantic checkpoint.
        upgrade_plugin_savepoint(true, 2026083007, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083008) {
        // Program 3 Gate UX1: read-only Past, Present, and Future dashboard composition checkpoint.
        upgrade_plugin_savepoint(true, 2026083008, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083100) {
        // Program 3 Gate UX2: read-only learner UX simplification checkpoint.
        upgrade_plugin_savepoint(true, 2026083100, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083101) {
        // Program 3 Gate UX3: immutable, versioned staff intervention ledger.
        $table = new xmldb_table('flwcupkp_intervention');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('serieskey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('supersedesid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('unitcode', XMLDB_TYPE_CHAR, '40');
        $table->add_field('interventiontype', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL);
        $table->add_field('targettype', XMLDB_TYPE_CHAR, '20');
        $table->add_field('targetid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('objectid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('actioncode', XMLDB_TYPE_CHAR, '40');
        $table->add_field('payloadjson', XMLDB_TYPE_TEXT);
        $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('policyversion', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('series_version_uix', XMLDB_INDEX_UNIQUE, ['serieskey', 'version']);
        $table->add_index('learner_scope_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'unitcode']);
        $table->add_index('type_status_ix', XMLDB_INDEX_NOTUNIQUE, ['interventiontype', 'status']);
        $table->add_index('createdby_ix', XMLDB_INDEX_NOTUNIQUE, ['createdby']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026083101, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026083102) {
        // Program 3 Gate F1: read-only full integrated production validation checkpoint.
        upgrade_plugin_savepoint(true, 2026083102, 'local', 'flwcupkp');
    }

    return true;
}
