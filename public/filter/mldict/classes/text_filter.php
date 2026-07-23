<?php
// This file is part of Moodle - http://moodle.org/

namespace filter_mldict;

use local_mldict\local\dictionary;

defined('MOODLE_INTERNAL') || die();

class text_filter extends \moodle_text_filter {
    /** @var array<string, array<int, array<string, string>>> */
    private static array $compiledpayloadcache = [];

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

        $casesensitive = (bool)get_config('filter_mldict', 'casesensitive');
        $payload = self::get_payload_cached($maxterms, $casesensitive);
        if (!$payload) {
            return $text;
        }

        return $this->replace_in_text_nodes($text, $payload);
    }

    /**
     * Load and cache filter payload for current request and shared payload cache.
     *
     * @param int $maxterms
     * @param bool $casesensitive
     * @return array<int, array<string, string>>
     */
    private static function get_payload_cached(int $maxterms, bool $casesensitive): array {
        $maxterms = max(1, min($maxterms, 800));
        $cachekey = $maxterms . ':' . ($casesensitive ? 'cs' : 'ic');

        if (!array_key_exists($cachekey, self::$compiledpayloadcache)) {
            self::$compiledpayloadcache[$cachekey] = dictionary::get_filter_payload_cached($maxterms, $casesensitive);
        }

        return self::$compiledpayloadcache[$cachekey];
    }

    private function replace_in_text_nodes(string $html, array $payload): string {
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
            $result .= $insideignored ? $part : $this->replace_terms($part, $payload);
        }
        return $result;
    }

    private function replace_terms(string $text, array $payload): string {
        foreach ($payload as $entry) {
            if ($entry['pattern'] === '' || $entry['url'] === '') {
                continue;
            }

            $text = preg_replace_callback($entry['pattern'], function(array $matches) use ($entry): string {
                return \html_writer::link(new \moodle_url($entry['url']), s($matches[1]), [
                    'class' => 'local-mldict-autolink',
                    'title' => s($entry['title']),
                ]);
            }, $text, 1);
        }
        return $text;
    }
}
