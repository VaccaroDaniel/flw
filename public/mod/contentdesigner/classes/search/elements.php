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
 * Search area for mod_contentdesigner elements.
 *
 * @package    mod_contentdesigner
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_contentdesigner\search;

use context;
use core_search\document;
use mod_contentdesigner\editor;

/**
 * Search area for mod_contentdesigner elements.
 */
class elements extends \core_search\base_mod {

    /**
     * Returns a recordset with all required elements information.
     *
     * @param int $modifiedfrom
     * @param \context|null $context Optional context to restrict scope of returned results
     * @return moodle_recordset|null Recordset (or null if no results)
     */
    public function get_document_recordset($modifiedfrom = 0, ?context $context = null) {
        global $DB;

        list ($contextjoin, $contextparams) = $this->get_context_restriction_sql($context, 'contentdesigner', 'c');
        if ($contextjoin === null) {
            return null;
        }

        // Generate additional SQL for searching cdelements.
        $additional = (object) $this->generate_additional_sql();

        $params = [$modifiedfrom];

        $sql = "SELECT co.*, co.instance, co.element, cp.title as chaptertitle,
                cp.id as chapterid, c.id as contentdesignerid, c.name as modname, ce.shortname as elementname,
                    $additional->select
                    co.timemodified, c.course, co.id
                    FROM {contentdesigner_options} co
                    JOIN {contentdesigner_elements} ce ON ce.id = co.element
                    LEFT JOIN {contentdesigner_content} cc ON cc.element = co.element AND cc.instance = co.instance
                    LEFT JOIN {cdelement_chapter} cp ON cp.id = cc.chapter
                    LEFT JOIN {contentdesigner} c ON cc.contentdesignerid = c.id
                    $contextjoin
                    $additional->joins
                    WHERE co.timemodified > ? ORDER BY co.timemodified ASC";

        return $DB->get_recordset_sql($sql, array_merge($contextparams, $params));
    }

    /**
     * Returns the document for a particular content designer element.
     *
     * @param \stdClass $record A record containing, at least, the indexed document id and a modified timestamp
     * @param array $options Options for document creation
     * @return \core_search\document|bool
     */
    public function get_document($record, $options = []) {
        global $DB;

        try {

            if (empty($record->id) || empty($record->contentdesignerid)) {
                $table = 'cdelement_' . $record->elementname;
                $contentdesignerid = $DB->get_field($table, 'contentdesignerid', ['id' => $record->instance], MUST_EXIST);
                $record->contentdesignerid = $contentdesignerid ?? null;
                $record->course = $DB->get_field('contentdesigner', 'course', ['id' => $record->contentdesignerid], MUST_EXIST);
            }

            $cm = $this->get_cm('contentdesigner', $record->contentdesignerid, $record->course);
            $context = \context_module::instance($cm->id);

        } catch (\dml_missing_record_exception $ex) {
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
                $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\dml_exception $ex) {
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, 'mod_contentdesigner', 'elements');
        $doc->set('title', content_to_text($record->title ?: $record->chaptertitle, false));
        $doc->set('content', content_to_text($record->content ?: '', $record->contentformat ?? FORMAT_HTML));
        $doc->set('description1', content_to_text($record->description, $record->descriptionformat));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $record->course);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && ($options['lastindexedtime'] < $record->timecreated)) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }

        return $doc;
    }

    /**
     * Generate additional SQL for searching elements.
     *
     * @return array An array with 'select' and 'joins' keys containing SQL fragments.
     */
    public function generate_additional_sql() {

        list($titlesql, $contentsql, $contentformatsql, $joinsql) = self::get_elements_search_areas();

        if (empty($titlesql) || empty($contentsql) || empty($joinsql)) {
            return ['select' => '', 'joins' => ''];
        }

        $select = " CASE " . implode(' ', $titlesql) . " ELSE NULL END AS title, ";
        $select .= " CASE " . implode(' ', $contentsql) . " ELSE NULL END AS content, ";

        $select .= !empty($contentformatsql) ?
            " CASE " . implode(' ', $contentformatsql) . " ELSE NULL END AS contentformat, " : '';

        return [
            'select' => $select,
            'joins' => implode(' ', $joinsql),
        ];
    }

    /**
     * Fetch the file areas from the elements. Fetch the fileareas and concat the element component name with filearea.
     * Use this function and define the fileareas Which is uses the mod_contentdesigner as the component for storing the files.
     *
     * @return array List of filearea.
     */
    public static function get_elements_search_areas() {
        global $DB;

        $plugins = editor::get_elements();

        $tablealias = 'cdelem';
        $i = 0;

        $prefix = $DB->get_prefix();
        foreach ($plugins as $plugin => $version) {
            $elementobj = editor::get_element($plugin, null);
            $areafiles = (method_exists($elementobj, 'search_area_list')) ? $elementobj->search_area_list() : [];

            // No fileareas defined for this element, use the default table for store the title.
            if (empty($areafiles)) {
                $alias = $tablealias . $i++;
                $tablename = $prefix . 'cdelement_' . $plugin;
                $titlesql[] = " WHEN ce.shortname = '{$plugin}' THEN {$alias}.title ";
                $joinsql[] = " LEFT JOIN {$tablename} AS {$alias} ON {$alias}.id = co.instance AND ce.shortname='{$plugin}' ";
                continue;
            }

            foreach ($areafiles as $table => $filearea) {
                $alias = $tablealias . $i++;
                $tablename = $prefix . $table;

                $format = explode(',', $filearea);

                $titlesql[] = " WHEN ce.shortname = '{$plugin}' THEN {$alias}.title ";
                $contentsql[] = " WHEN ce.shortname = '{$plugin}' THEN {$alias}.{$format[0]} ";
                if (!empty($format[1])) {
                    $contentformatsql[] = " WHEN ce.shortname = '{$plugin}' THEN {$alias}.{$format[1]} ";
                }

                $joinsql[] = " LEFT JOIN {$tablename} AS {$alias} ON {$alias}.id = co.instance AND ce.shortname='{$plugin}' ";
            }
        }

        return [
            $titlesql ?? [],
            $contentsql ?? [],
            $contentformatsql ?? [],
            $joinsql ?? [],
        ];
    }

    /**
     * Can the current user see the document.
     *
     * @param int $id The internal search area entity id.
     * @return bool True if the user can see it, false otherwise
     */
    public function check_access($id) {
        global $DB;

        try {

            // Remove the deleted elements from the index.
            if (!$DB->record_exists('contentdesigner_options', ['id' => $id])) {
                return \core_search\manager::ACCESS_DELETED;
            }

            $content = $DB->get_record('search_simpledb_index', ['itemid' => $id, 'areaid' => $this->areaid], '*', MUST_EXIST);
            $context = \context::instance_by_id($content->contextid);
            $cm = get_coursemodule_from_id('contentdesigner', $context->instanceid);
            $cminfo = $this->get_cm('contentdesigner', $cm->instance, $cm->course);

        } catch (\dml_missing_record_exception $ex) {
            return \core_search\manager::ACCESS_DELETED;
        } catch (\dml_exception $ex) {
            return \core_search\manager::ACCESS_DENIED;
        }

        if ($cminfo->uservisible === false) {
            return \core_search\manager::ACCESS_DENIED;
        }

        if (!has_capability('mod/contentdesigner:view', $context)) {
            return \core_search\manager::ACCESS_DENIED;
        }

        return \core_search\manager::ACCESS_GRANTED;
    }

    /**
     * Returns a url to the chapter.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
        global $DB;

        $contextmodule = \context::instance_by_id($doc->get('contextid'));
        $params = ['id' => $contextmodule->instanceid];
        $url = new \moodle_url('/mod/contentdesigner/view.php', $params);

        $chapter = $DB->get_record_sql('
            SELECT cc.chapter FROM {contentdesigner_options} co
            JOIN {contentdesigner_content} cc ON co.element = cc.element AND co.instance = cc.instance
            WHERE co.id=:id', ['id' => $doc->get('itemid')], IGNORE_MISSING);

        if ($chapter) {
            $url->param('chapterid', $chapter->chapter);
            $url->set_anchor('chapters-list-' . $chapter->chapter);
        }

        return $url;
    }

    /**
     * Returns a url to the content designer.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {

        if ($url = $this->get_doc_url($doc)) {
            return $url;
        }

        $contextmodule = \context::instance_by_id($doc->get('contextid'));
        return new \moodle_url('/mod/contentdesigner/view.php', ['id' => $contextmodule->instanceid]);
    }

    /**
     * Return the context info required to index files for this search area.
     *
     * @return array
     */
    public function get_search_fileareas() {
        $fileareas = ['description']; // Filearea.
        return $fileareas;
    }

    /**
     * Returns the document title to display.
     *
     * Allow to customize the document title string to display.
     *
     * @param \core_search\document $doc
     * @return string Document title to display in the search results page
     */
    public function get_document_display_title(\core_search\document $doc) {
        $cm = get_coursemodule_from_id('contentdesigner', \context::instance_by_id($doc->get('contextid'))->instanceid);
        return format_text($cm->name, FORMAT_HTML) . ': ' . $doc->get('title');
    }
}
