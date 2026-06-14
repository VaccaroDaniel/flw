<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

class block_mldict extends block_base {
    public function init(): void {
        $this->title = get_string('pluginname', 'block_mldict');
    }

    public function applicable_formats(): array {
        return ['all' => true];
    }

    public function has_config(): bool {
        return false;
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $url = new moodle_url('/local/mldict/index.php');

        $form = html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'mldict-block-form']);
        $form .= html_writer::label(get_string('searchdictionary', 'block_mldict'), 'block-mldict-q', false, ['class' => 'accesshide']);
        $form .= html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'q',
            'id' => 'block-mldict-q',
            'placeholder' => get_string('searchdictionary', 'block_mldict'),
        ]);
        $form .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('search', 'block_mldict'),
            'class' => 'btn btn-secondary btn-sm',
        ]);
        $form .= html_writer::end_tag('form');

        $this->content->text = $form;
        $this->content->footer = html_writer::link($url, get_string('opendictionary', 'block_mldict'));
        return $this->content;
    }
}
