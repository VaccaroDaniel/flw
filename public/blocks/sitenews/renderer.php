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
 * @package    block_sitenews
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v4 or later
 * @author     Jan Eberhardt <eberhardt@math.tu-berlin.de>
 */

require_once($CFG->dirroot.'/blocks/sitenews/locallib.php');

class block_sitenews_renderer extends plugin_renderer_base {

    /**
     * Prints the site news
     *
     * @param array $sitenews
     * @return string
     */
    public function sitenews(stdClass $newsforum, $items) {
        global $SITE, $USER;

        $coursemodule = get_coursemodule_from_instance('forum', $newsforum->id);
        $context = context_module::instance($coursemodule->id);

        $entityfactory = mod_forum\local\container::get_entity_factory();
        $forumentity = $entityfactory->get_forum_from_stdclass($newsforum, $context, $coursemodule, $SITE);

        $rendererfactory = mod_forum\local\container::get_renderer_factory();
        $discussionsrenderer = $rendererfactory->get_frontpage_news_discussion_list_renderer($forumentity);
        $cm = \cm_info::create($coursemodule);

        $content = $discussionsrenderer->render($USER, $cm, null, null, 0, $items);
        $content = html_writer::tag('div', $content, ['id' => 'site-news-forum']);

        return $content;
    }

    /**
     * Prints the editing header
     *
     * @param number $items Number of news items to display
     * @return string
     */
    public function editing_bar_head($selected = 0) {
        $output = $this->output->box_start("notice");

        $url = new moodle_url("/my/index.php");
        $options = range(0,10);
        $options[0] = get_string("preset", "block_sitenews");
        $select = new single_select($url, "mynewsitems", $options, $selected, array());
        $select->method = 'post';
        $select->set_label(get_string("newsitemsnumber") . ":");
        $output .= $this->output->render($select);

        $output .= $this->output->box_end();
        return $output;
    }
}