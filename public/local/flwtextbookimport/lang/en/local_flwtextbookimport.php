<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'FLW textbook importer';
$string['activityidnumberexists'] = 'A generated activity with idnumber "{$a}" already exists. Use --reuse-modules to skip existing generated modules.';
$string['composersectionunsupported'] = 'The learner-ready composer currently supports section {$a} only.';
$string['courseshortnameexists'] = 'A course with shortname "{$a}" already exists. Use --reuse-course to update its section summaries.';
$string['defaultpackagepath'] = 'Default dry-run package path';
$string['defaultpackagepath_desc'] = 'Local path to the reviewed textbook dry-run JSON package used by the review dashboard.';
$string['filenotreadable'] = 'The input package is not readable: {$a}';
$string['flwhandoff'] = 'FLW handoff';
$string['invalidjson'] = 'The input package is not valid JSON: {$a}';
$string['invalidschema'] = 'The input package schema is not supported: {$a}';
$string['loadpackage'] = 'Load package';
$string['missingcategorypath'] = 'The input package does not include a usable category path.';
$string['missingcoursekey'] = 'The input package course data is missing required key: {$a}';
$string['missinginput'] = 'Missing required --input path.';
$string['missingpackagekey'] = 'The input package is missing required key: {$a}';
$string['missingsections'] = 'The input package does not include any sections.';
$string['opencourse'] = 'Open generated course';
$string['previewlesson'] = 'Preview Lesson {$a}';
$string['publishlesson'] = 'Publish Lesson {$a}';
$string['publishlessonconfirm'] = 'Publish only Lesson {$a} for students';
$string['publishlessonresult'] = 'Lesson {$a->section} visibility updated: {$a->visible} visible modules, {$a->hidden} hidden modules.';
$string['reviewdashboard'] = 'Textbook review dashboard';
$string['reviewnav'] = 'Textbook import review';
$string['reviewsaved'] = '{$a} review rows updated.';
$string['sectionnotfound'] = 'The input package does not include section {$a}.';
$string['unpublishlesson'] = 'Return Lesson {$a} to review';
$string['unsafeinputpackage'] = 'The input package is not marked as dry-run safe, so it cannot be imported.';
