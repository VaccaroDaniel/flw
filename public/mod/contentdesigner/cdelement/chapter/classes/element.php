<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Extended class of elements for chapter. it contains major part of editor element content
 *
 * @package   cdelement_chapter
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_chapter;

defined('MOODLE_INTERNAL') || die();

use mod_contentdesigner\editor;
use moodle_exception;

require_once($CFG->dirroot . '/mod/contentdesigner/cdelement/chapter/lib.php');

/**
 * Definitions of chapter element and it behaviours.
 */
class element extends \mod_contentdesigner\elements {
    /**
     * Shortname of the element.
     */
    const SHORTNAME = 'chapter';

    /**
     * Element name which is visbile for the users
     *
     * @return string
     */
    public function element_name() {
        return get_string('pluginname', 'cdelement_chapter');
    }

    /**
     * Element shortname which is used as identical purpose.
     *
     * @return string
     */
    public function element_shortname() {
        return self::SHORTNAME;
    }

    /**
     * Icon of the element.
     *
     * @param renderer $output
     * @return string HTML fragment
     */
    public function icon($output) {
        return $output->pix_icon('i/folder', get_string('pluginname', 'cdelement_chapter'));
    }

    /**
     * Element form element definition.
     *
     * @param moodle_form $mform
     * @param genreal_element_form $formobj
     * @return void
     */
    public function element_form(&$mform, $formobj) {

        // General settigns.
        $strrequired = get_string('required');
        $mform->addElement('text', 'title', get_string('elementtitle', 'mod_contentdesigner'), 'maxlength="100" size="30"');
        $mform->addRule('title', $strrequired, 'required', null, 'client');
        $mform->setType('title', PARAM_NOTAGS);

        // Display title.
        $default = get_config('cdelement_chapter', 'chaptertitlestatus');
        $mform->addElement('checkbox', 'titlestatus', get_string('titlestatus', 'mod_contentdesigner'));
        $mform->setDefault('titlestatus', $default ?: 0);
        $mform->addHelpButton('titlestatus', 'titlestatus', 'mod_contentdesigner');
        $chapatertitle = get_config('cdelement_chapter', 'chapatertitle');

        // Visibility for General element.
        $visibleoptions = [
            1 => get_string('visible'),
            0 => get_string('hidden', 'mod_contentdesigner'),
        ];
        $default = get_config('cdelement_chapter', 'visibility');
        $mform->addElement('select', 'visible', get_string('visibility', 'mod_contentdesigner'), $visibleoptions);
        $mform->addHelpButton('visible', 'visibility', 'mod_contentdesigner');
        $mform->setDefault('visible', $default);

        if (cdelement_chapter_has_learningtools()) {
            // Learning Tools setting.
            $ltoolsoptions = [
                0 => get_string('disabled', 'mod_contentdesigner'),
                1 => get_string('enabled', 'mod_contentdesigner'),
            ];
            $default = get_config('cdelement_chapter', 'learningtools');
            $mform->addElement('select', 'learningtools', get_string('learningtools', 'mod_contentdesigner'), $ltoolsoptions);
            $mform->addHelpButton('learningtools', 'learningtools', 'mod_contentdesigner');
            $mform->setDefault('learningtools', $default);
        }
    }

    /**
     * Verify the element is supports the content render method.
     *
     * @return bool
     */
    public function supports_content() {
        return false;
    }

    /**
     * Verify the elements the standard general options list.
     *
     * @return bool
     */
    public function supports_standard_elements() {
        // By default all the elmenets will supports the standard options.
        return false;
    }

    /**
     * Initiate the element js for the view page.
     *
     * @return void
     */
    public function initiate_js() {
        global $PAGE;
        $PAGE->requires->js_call_amd('cdelement_chapter/chapter', 'init', []);
    }

    /**
     * Get the default chapter instance for the current course module. if not found create new one and use it as default one.
     *
     * @param int $contentdesignerid Content designer instance id.
     * @param bool $create
     * @return bool
     */
    public function get_default($contentdesignerid, $create = false) {
        global $DB;
        if ($record = $DB->get_record('cdelement_chapter', ['contentdesignerid' => $contentdesignerid], '*', IGNORE_MULTIPLE)) {
            return $record->id;
        }

        if ($create) {
            $id = $this->create_basic_instance($contentdesignerid);
            return $id;
        }
        return false;
    }

    /**
     * Create the basic instance for the element. Override this function if need to add custom changes.
     *
     * @param int $contentdesignerid Contnet deisnger instance id.
     * @return int Element instance id.
     */
    public function create_basic_instance($contentdesignerid) {
        global $DB;

        $record = [
            'contentdesignerid' => $contentdesignerid,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        if ($this->is_table_exists()) {
            $lastelement = (int) $DB->get_field_sql('SELECT max(position) from {cdelement_chapter}
                WHERE contentdesignerid = ?', [$this->cm->instance]
            );
            $record['position'] = $lastelement ? $lastelement + 1 : 1;
            $result = $DB->insert_record($this->tablename, $record);
            $data = [];
            $fields = $this->get_options_fields();
            foreach ($fields as $field) {
                $globalvalues = get_config('mod_contentdesigner', $field);
                $data[$field] = $globalvalues ?? '';
            }
            $data['element'] = $this->elementid;
            $data['instance'] = $result;
            $data['timecreated'] = time();
            if (!$DB->record_exists('contentdesigner_options', ['instance' => $result, 'element' => $this->elementid])) {
                $DB->insert_record('contentdesigner_options', $data);
            }
            return $result;
        } else {
            throw new \moodle_exception('tablenotfound', 'contentdesigner');
        }
    }

    /**
     * Update the element instance. Override the function in elements element class to add custom rules.
     *
     * @param stdclass $data
     * @return int Element instance id.
     */
    public function update_instance($data) {
        global $DB;

        if ($data->instanceid == false) {
            $data->timemodified = time();
            $data->timecreated = time();
            if ($data->chapterid) {
                $lastelement = $DB->get_field('cdelement_chapter', 'position', ['id' => $data->chapterid]);
                $DB->execute('UPDATE {cdelement_chapter} SET position=position+1
                WHERE position > ? AND contentdesignerid = ?', [$lastelement, $this->cm->instance]);
            } else {
                $lastelement = (int) $DB->get_field_sql('SELECT max(position) from {cdelement_chapter}
                    WHERE contentdesignerid = ?', [$this->cm->instance]);
            }
            $data->position = $lastelement ? $lastelement + 1 : 1;
            return $DB->insert_record($this->tablename, $data);
        } else {
            $data->timecreated = time();
            $data->id = $data->instanceid;
            $data->titlestatus = $data->titlestatus ?? 0;
            if ($data->chapterid) {
                $lastelement = $DB->get_field('cdelement_chapter', 'position', ['id' => $data->chapterid]);
                $DB->execute('UPDATE {cdelement_chapter} SET position=position+1
                WHERE position > ? AND contentdesignerid = ?', [$lastelement, $this->cm->instance]);
            } else {
                $lastelement = (int) $DB->get_field_sql('SELECT max(position) from {cdelement_chapter}
                    WHERE contentdesignerid = ?', [$this->cm->instance]);
            }

            $data->position = $data->position ?: $lastelement + 1;
            if ($DB->update_record($this->tablename, $data)) {
                return $data->id;
            }
        }
    }

    /**
     * Set element instance for the given chapter as contents.
     *
     * @param int $chapterid Chapter id
     * @param int $contentid Content id
     * @return bool
     */
    public function set_elements($chapterid, $contentid) {
        global $DB;

        if ($chapter = $DB->get_record('cdelement_chapter', ['id' => $chapterid])) {
            $contents = isset($chapter->contents) ? array_filter(explode(',', $chapter->contents)) : [];
            $contents[] = $contentid;
            $DB->set_field('cdelement_chapter', 'contents', implode(',', array_filter($contents)), ['id' => $chapterid]);
            $DB->set_field('cdelement_chapter', 'timemodified', time(), ['id' => $chapterid]);
        }
        return false;
    }

    /**
     * Render the chapters for the course module.
     *
     * @param bool $visible Load only visible elements.
     * @param bool $render Return the elements with rendered html.
     * @param bool $chapterafter It is need to load the chapters after the given chapter.
     * @return array
     */
    public function get_chapters_data($visible = false, $render = false, $chapterafter = false) {
        global $DB, $USER;
        if (empty($this->cm)) {
            throw new \moodle_exception('coursemoduleidmissing', 'format_levels');
        }
        $context = \context_system::instance();
        $list = []; // List of chapters.
        $condition = ['contentdesignerid' => $this->cm->instance];
        $condition += $visible ? ['visible' => 1] : [];
        if ($chapters = $DB->get_records('cdelement_chapter', $condition, 'position ASC')) {
            $chapterreached = false;
            foreach ($chapters as $chapterid => $chapter) {
                // Find the chapter is reached, checks only chapterafter enabled.
                if ($chapterafter && !             $chapterreached) {
                    // Set the chapter reached to load the chapters from next chapter.
                    $chapterreached = ($chapterafter == $chapterid);
                    continue;
                }
                $chapter->chaptertitle = $chapter->title;
                $chapter->title = $this->title_editable($chapter) ?: $this->info()->name;
                list($prevent, $contents, $learningtools) = $this->generate_chapter_content($chapter, $visible, $render);
                if ($visible && empty($contents) && !$chapter->titlestatus) {
                    continue;
                }

                $completion = $DB->get_record('cdelement_chapter_completion', ['instance' => $chapter->id, 'userid' => $USER->id]);
                $chapterprevent = ($render && $this->prevent_nextelements($chapter));

                $element = \mod_contentdesigner\editor::get_element('chapter', $this->cmid);
                $editurl = new \moodle_url('/mod/contentdesigner/element.php', [
                    'cmid' => $this->cmid,
                    'element' => $element->shortname,
                    'id' => $chapter->id,
                    'sesskey' => sesskey(),
                ]);

                $copyurl = new \moodle_url('/mod/contentdesigner/editor.php', [
                    'id' => $this->cmid,
                    'instanceid' => $chapter->id,
                    'element' => $element->shortname,
                    'action' => 'copy',
                    'sesskey' => sesskey(),
                ]);

                $list[] = [
                    'instancedata' => $chapter,
                    'info' => $this->info(),
                    'editurl' => $editurl,
                    'contents' => $contents,
                    'count' => count($contents),
                    'prevent' => $prevent,
                    'chapterprevent' => $chapterprevent,
                    'chaptercta' => ($render) ?: false,
                    'completion' => isset($completion->completion) && $completion->completion ? true : false,
                    'copyurl' => $copyurl,
                    'learningtools' => $learningtools, // Add the learning tools flag.
                    'hasbookmarkcapability' => (!empty($chapter->learningtools))
                        ? has_capability('ltool/bookmarks:createbookmarks', $context) : false,
                    'hasnotecapability' => (!empty($chapter->learningtools))
                        ? has_capability('ltool/note:createnote', $context) : false,
                ];
                // Prevent the next chapters when user needs to complete any of activities.
                if ($prevent || $chapterprevent) {
                    break;
                }
            }
        }
        return $list;
    }

    /**
     * Prevent the upcoming elements if the chapter not completed.
     *
     * @param stdclass $chapter Chapter element instance data.
     * @return bool
     */
    public function prevent_nextelements($chapter): bool {
        global $USER;

        if (has_capability('mod/contentdesigner:viewcontenteditor', $this->context)) {
            return false;
        }

        return !             $this->is_chaptercompleted($chapter->id);
    }

    /**
     * Find the user is completed the chapter.
     * @param int $chapterid Instance data of chapter.
     * @return bool
     */
    public function is_chaptercompleted($chapterid): bool {
        global $USER, $DB;

        if ($record = $DB->get_record('cdelement_chapter_completion', ['instance' => $chapterid, 'userid' => $USER->id])) {
            return $record->completion ? true : false;
        }
        return false;
    }

    /**
     * Gerneate chapter related elements.
     *
     * @param stdclass $chapter
     * @param bool $visible Fetch only visible elements.
     * @param bool $render Render the element instance to student view.
     * @return array
     */
    public function generate_chapter_content($chapter, $visible = false, $render = false) {
        global $DB, $USER, $PAGE;

        $list = [];
        $prevent = false;
        $record = $DB->get_record('contentdesigner', ['id' => $this->cm->instance]);
        $url = new \moodle_url('/mod/contentdesigner/view.php', ['id' => $this->cm->id]);

        // Learning tools data.
        $learningtools = [
            'show' => false,
            'chapterid' => $chapter->id,
            'bookmarked' => false,
            'notescount' => 0,
            'userid' => $USER->id,
            'sesskey' => sesskey(),
            'course' => $record->course,
            'coursemodule' => $this->cm->id,
            'contextlevel' => CONTEXT_MODULE,
            'pagetype' => $PAGE->pagetype,
            'pageurl' => $url ."#chapters-list-" . $chapter->id,
        ];

        // Check if learning tools should be displayed.
        if (!empty($chapter->learningtools) && cdelement_chapter_has_learningtools()) {
            $context = \context_module::instance($this->cmid);
            $hasbookmarkcapability = has_capability('ltool/bookmarks:createbookmarks', $context);
            $hasnotescapability = has_capability('ltool/note:createnote', $context);

            // Only show learning tools if user has at least one capability.
            if ($hasbookmarkcapability || $hasnotescapability) {
                $learningtools['show'] = true;
                // Check if the chapter is bookmarked by the user.
                $learningtools['bookmarked'] = $DB->record_exists('ltool_bookmarks_data', [
                    'itemtype' => 'chapter',
                    'itemid' => $chapter->id,
                    'userid' => $USER->id,
                ]);

                $learningtools['notescount'] = $DB->count_records('ltool_note_data', [
                    'itemtype' => 'chapter',
                    'itemid' => $chapter->id,
                    'userid' => $USER->id,
                ]);
            }
        }

        if (empty($chapter->contents)) {
            return [$prevent, $list, $learningtools];
        }
        $contents = explode(',', $chapter->contents);
        $sql  = 'SELECT cc.*, ce.id as elementid, ce.shortname as elementname FROM {contentdesigner_content} cc
                JOIN {contentdesigner_elements} ce ON ce.id = cc.element
                WHERE cc.chapter = ? ORDER BY position ASC';
        $params = ['chapterid' => $chapter->id];

        $contents = $DB->get_records_sql($sql, $params);
        foreach ($contents as $content) {
            $cm = $this->get_cm_from_modinstance($content->contentdesignerid);
            $element = \mod_contentdesigner\editor::get_element($content->elementname, $cm->id);
            $editor = \mod_contentdesigner\editor::get_editor($this->cmid);
            $instance = $element->get_instance($content->instance, $visible);
            if ($instance) {
                $instance->titleplain = $instance->title;
                $instance->title = $element->title_editable($instance) ?: $element->info()->name;
                $option = $editor->get_option($instance->id, $element->elementid);
                // Load the element options classes to instance.
                $element->generate_element_classes($instance, $option);

                // Verify this element supports replace on refresh.
                $instance->replaceonrefresh = $element->supports_replace_onrefresh();

                // Use Mutation of instance in render function of element to add element classes.
                $contenthtml = ($render) ? $element->render_element($instance) : '';
                $editurl = new \moodle_url('/mod/contentdesigner/element.php', [
                    'cmid' => $this->cmid,
                    'element' => $element->shortname,
                    'id' => $instance->id,
                    'sesskey' => sesskey(),
                ]);

                $copyurl = new \moodle_url('/mod/contentdesigner/editor.php', [
                    'id' => $this->cmid,
                    'instanceid' => $instance->id,
                    'element' => $element->shortname,
                    'action' => 'copy',
                    'sesskey' => sesskey(),
                ]);

                $list[] = (array) $content + [
                    'info' => $element->info(),
                    'instancedata' => $instance,
                    'option' => $option,
                    'editurl' => $editurl,
                    'content' => $contenthtml,
                    'copyurl' => $copyurl,
                ];

                // Prevent the elements next to the manatory elements.
                if ($render && $element->prevent_nextelements($instance)) {
                    $prevent = true;
                    break;
                }
            }
        }

        return [$prevent, $list, $learningtools];
    }

    /**
     * Build the progress of the chapter completion.
     *
     * @return string HTML of the progress bar.
     */
    public function build_progress() {
        global $DB, $OUTPUT, $USER;

        $sql = "SELECT ec.*, ecc.completion FROM {cdelement_chapter} ec
            LEFT JOIN {cdelement_chapter_completion} ecc ON ec.id = ecc.instance AND ecc.userid=:userid
            WHERE ec.contentdesignerid=:contentdesignerid AND ec.visible = 1 AND ec.id IN (
                SELECT chapter FROM {contentdesigner_content} cc
            ) ORDER BY position ASC";
        $records = $DB->get_records_sql($sql, ['userid' => $USER->id, 'contentdesignerid' => $this->cm->instance]);

        $data = [
            'chapters' => array_values($records),
            'contentdesignerid' => $this->cm->instance,
            'cmid' => $this->cmid,
        ];
        return $OUTPUT->render_from_template('cdelement_chapter/progressbar', $data);
    }

    /**
     * Update the position of the content in chapters.
     *
     * @param int $chapterid  Chapter id.
     * @param string $contents Contents in order position.
     * @return bool
     */
    public function update_postion($chapterid, $contents) {
        global $DB;

        $instance = $this->get_instance($chapterid);
        $instance->contents = $contents;
        try {
            $transaction = $DB->start_delegated_transaction();
            if (!             empty($contents)) {
                $list = explode(',', $contents);
                $position = 1;
                foreach ($list as $item) {
                    $record = (object) [
                        'id' => $item,
                        'position' => $position,
                        'chapter' => $chapterid,
                    ];
                    $DB->update_record('contentdesigner_content', $record);
                    $position += 1;
                }
            }
            // Update the chapter contents in cdelement_chapter table.
            $this->update_chapter_contents($chapterid);
            $transaction->allow_commit();
        } catch (moodle_exception $ex) {
            $transaction->rollback($ex);
        }
        return $DB->update_record('cdelement_chapter', $instance);
    }

    /**
     * Move the chapter position in the given position.
     *
     * @param string $chapters Chapters list in the position
     * @return bool
     */
    public function move_chapter($chapters) {
        global $DB;

        try {
            $transaction = $DB->start_delegated_transaction();
            if (!empty($chapters)) {
                $list = explode(',', $chapters);
                $position = 1;
                foreach ($list as $item) {
                    $record = (object) [
                        'id' => $item,
                        'position' => $position,
                    ];
                    $status = $DB->update_record('cdelement_chapter', $record);
                    $position += 1;
                }
            }
            $transaction->allow_commit();
            return true;
        } catch (moodle_exception $ex) {
            $transaction->rollback($ex);
        }
    }

    /**
     * Update contents of chapter.
     *
     * @param int $chapterid Chapter id.
     * @return void
     */
    public function update_chapter_contents($chapterid) {
        global $DB;

        if ($contents = $DB->get_records('contentdesigner_content', ['chapter' => $chapterid])) {
            $contents = array_column($contents, 'id');
            $DB->update_record('cdelement_chapter', ['id' => $chapterid, 'contents' => implode(',', $contents)]);
        }
    }

    /**
     * Render the view of element instance, Which is displayed in the student view.
     *
     * @param stdclass $instance
     * @return bool
     */
    public function render($instance) {
        return false;
    }

    /**
     * Delete the element settings.
     *
     * @param int $instanceid
     * @return bool $status
     */
    public function delete_element($instanceid) {
        global $DB;
        try {
            $transaction = $DB->start_delegated_transaction();
            // Delete the element settings.
            if ($this->get_instance($instanceid)) {
                $DB->delete_records($this->tablename(), ['id' => $instanceid]);
                $DB->delete_records('cdelement_chapter_completion', ['instance' => $instanceid]);
            }

            $dbman = $DB->get_manager();
            // Deleted the Learningtools bookmarks and notes data.
            if ($dbman->table_exists('ltool_bookmarks_data') && $dbman->field_exists('ltool_bookmarks_data', 'itemid')) {
                $DB->delete_records('ltool_bookmarks_data', ['itemid' => $instanceid, 'itemtype' => 'chapter']);
            }

            if ($dbman->table_exists('ltool_note_data') && $dbman->field_exists('ltool_note_data', 'itemid')) {
                $DB->delete_records('ltool_note_data', ['itemid' => $instanceid, 'itemtype' => 'chapter']);
            }
            if ($contents = $DB->get_records('contentdesigner_content', ['chapter' => $instanceid])) {
                foreach ($contents as $key => $value) {
                    $element = editor::get_element($value->element, $this->cmid);
                    $element->delete_element($value->instance);
                }
            }
            if ($this->get_instance_options($instanceid)) {
                // Delete the element general settings.
                $DB->delete_records('contentdesigner_options', ['element' => $this->element_id(),
                    'instance' => $instanceid]);
            }
            $transaction->allow_commit();
        } catch (\Exception $e) {
            // Extra cleanup steps.
            $transaction->rollback($e); // Rethrows exception.
            throw new \moodle_exception('chapternotdeleted', 'cdelement_chapter');
        }
        return true;
    }
}
