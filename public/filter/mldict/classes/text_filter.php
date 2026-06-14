<?php
// This file is part of Moodle - http://moodle.org/

namespace filter_mldict;

use local_mldict\local\dictionary;

defined('MOODLE_INTERNAL') || die();

class text_filter extends \moodle_text_filter {
    public function filter($text, array $options = []) {
        if (empty($text) || stripos($text, 'local-mldict-autolink') !== false) {
            return $text;
        }

        if (!class_exists(dictionary::class)) {
            return $text;
        }

        $maxterms = (int)(get_config('filter_mldict', 'maxterms') ?: 500);
        if ($maxterms <= 0) {
            return $text;
        }

        $terms = dictionary::get_filter_terms($maxterms);
        if (!$terms) {
            return $text;
        }

        $casesensitive = (bool)get_config('filter_mldict', 'casesensitive');
        return $this->replace_in_text_nodes($text, $terms, $casesensitive);
    }

    private function replace_in_text_nodes(string $html, array $terms, bool $casesensitive): string {
        $parts = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return $html;
        }

        $insideignored = false;
        $result = '';
        foreach ($parts as $part) {
            if (preg_match('/^<\s*(a|script|style|textarea|code|pre)\b/i', $part)) {
                $insideignored = true;
                $result .= $part;
                continue;
            }
            if (preg_match('/^<\s*\/\s*(a|script|style|textarea|code|pre)\s*>/i', $part)) {
                $insideignored = false;
                $result .= $part;
                continue;
            }
            if (str_starts_with($part, '<')) {
                $result .= $part;
                continue;
            }
            $result .= $insideignored ? $part : $this->replace_terms($part, $terms, $casesensitive);
        }
        return $result;
    }

    private function replace_terms(string $text, array $terms, bool $casesensitive): string {
        foreach ($terms as $term) {
            $word = trim($term->headword ?? '');
            if ($word === '' || \core_text::strlen($word) < 2) {
                continue;
            }

            $quoted = preg_quote($word, '/');
            $flags = $casesensitive ? 'u' : 'iu';
            $pattern = '/(?<![\p{L}\p{N}_])(' . $quoted . ')(?![\p{L}\p{N}_])/' . $flags;
            $url = new \moodle_url('/local/mldict/view.php', ['id' => $term->id]);
            $titleparts = array_filter([$term->sourcelang ?? '', $term->partofspeech ?? '', $term->cefrlevel ?? '']);
            $title = implode(' · ', $titleparts);
            $text = preg_replace_callback($pattern, function(array $matches) use ($url, $title): string {
                return \html_writer::link($url, s($matches[1]), [
                    'class' => 'local-mldict-autolink',
                    'title' => s($title),
                ]);
            }, $text, 1);
        }
        return $text;
    }
}
