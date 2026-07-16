<?php
defined('MOODLE_INTERNAL') || die();

ob_start();
require($CFG->dirroot . '/theme/boost/layout/login.php');
$pagehtml = ob_get_clean();

$templatecontext = theme_flwacademy_extend_tools_context([
    'output' => $OUTPUT,
    'currentcategorytype' => '',
]);
$toolsgroup = $OUTPUT->render_from_template('theme_flwacademy/flw_tools_group', $templatecontext);
if ($toolsgroup !== '' && strpos($pagehtml, '</body>') !== false) {
    $pagehtml = str_replace('</body>', $toolsgroup . "\n</body>", $pagehtml);
} else {
    $pagehtml .= $toolsgroup;
}

echo $pagehtml;
