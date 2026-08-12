<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_mldict\local\dictionary;

require_login();
$context = context_system::instance();
require_capability('local/mldict:view', $context);

$edit = optional_param('edit', -1, PARAM_BOOL);
$q = optional_param('q', '', PARAM_TEXT);
$lang = optional_param('lang', null, PARAM_ALPHANUMEXT);
$lang = $lang === null ? dictionary::preferred_learning_language() : dictionary::normalise_lang_code($lang);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 50;

$url = new moodle_url('/local/mldict/index.php', ['q' => $q, 'lang' => $lang]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_other_editing_capability('local/mldict:manage');
$PAGE->set_title(get_string('pluginname', 'local_mldict'));
$PAGE->set_heading(get_string('pluginname', 'local_mldict'));
$PAGE->requires->css('/local/mldict/styles.css');
$PAGE->set_cacheable(false);

if ($edit !== -1 && confirm_sesskey() && has_capability('local/mldict:manage', $context)) {
    $USER->editing = $edit ? 1 : 0;
    redirect($url);
}

$output = $PAGE->get_renderer('core');
$isediting = $PAGE->user_is_editing() && has_capability('local/mldict:manage', $context);
if (has_capability('local/mldict:manage', $context)) {
    $PAGE->set_button($output->edit_button($url));
}
echo $output->header();

if ($isediting) {
    echo html_writer::start_div('local-mldict-actions');
    echo html_writer::link(new moodle_url('/local/mldict/edit.php'), get_string('addentry', 'local_mldict'), ['class' => 'btn btn-primary']);
    echo ' ';
    echo html_writer::link(new moodle_url('/local/mldict/import.php'), get_string('importcsv', 'local_mldict'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_div();

    $totalentries = dictionary::count_entries($q, $lang);
    $entries = dictionary::search_entries($q, $lang, $perpage, $page * $perpage);
    $pageurl = new moodle_url('/local/mldict/index.php', ['q' => $q, 'lang' => $lang]);

    $langoptions = ['' => get_string('alllanguages', 'local_mldict')] + dictionary::lang_options();
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $pageurl->out(false), 'class' => 'local-mldict-search']);
    echo html_writer::label(get_string('searchdictionary', 'local_mldict'), 'local-mldict-manage-q', false, ['class' => 'accesshide']);
    echo html_writer::empty_tag('input', [
        'type' => 'search',
        'name' => 'q',
        'id' => 'local-mldict-manage-q',
        'value' => s($q),
        'placeholder' => get_string('typeword', 'local_mldict'),
        'autocomplete' => 'off',
    ]);
    echo html_writer::select($langoptions, 'lang', $lang, false, ['id' => 'local-mldict-manage-lang']);
    echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');

    if (!$entries) {
        echo $output->notification(get_string('noentries', 'local_mldict'), 'info');
    } else {
        echo html_writer::start_div('local-mldict-management');
        if ($totalentries > $perpage) {
            echo $output->render(new paging_bar($totalentries, $page, $perpage, $pageurl));
        }

        $table = new html_table();
        $table->attributes['class'] = 'generaltable local-mldict-management-table';
        $table->head = [
            get_string('headword', 'local_mldict'),
            get_string('sourcelang', 'local_mldict'),
            get_string('partofspeech', 'local_mldict'),
            get_string('definition', 'local_mldict'),
            '',
        ];
        foreach ($entries as $entry) {
            $definition = trim($entry->definition ?? '');
            if (core_text::strlen($definition) > 120) {
                $definition = core_text::substr($definition, 0, 120) . '...';
            }
            $actions = html_writer::link(new moodle_url('/local/mldict/view.php', ['id' => $entry->id]), get_string('view'));
            $actions .= ' | ' . html_writer::link(new moodle_url('/local/mldict/edit.php', ['id' => $entry->id]), get_string('edit'));
            $actions .= ' | ' . html_writer::link(new moodle_url('/local/mldict/delete.php', ['id' => $entry->id]), get_string('delete'));
            $table->data[] = [
                html_writer::link(new moodle_url('/local/mldict/view.php', ['id' => $entry->id]), format_string($entry->headword)),
                s(dictionary::lang_label($entry->sourcelang)),
                s($entry->partofspeech),
                s($definition),
                $actions,
            ];
        }
        echo html_writer::table($table);

        if ($totalentries > $perpage) {
            echo $output->render(new paging_bar($totalentries, $page, $perpage, $pageurl));
        }
        echo html_writer::end_div();
    }

    echo $output->footer();
    exit;
}

$langoptions = ['' => get_string('alllanguages', 'local_mldict')] + dictionary::lang_options();
echo html_writer::start_div('local-mldict-page');
echo html_writer::div(
    html_writer::div(
        html_writer::span(get_string('wordlab', 'local_mldict'), 'local-mldict-hero-kicker') .
        html_writer::tag('h1', get_string('pluginname', 'local_mldict')) .
        html_writer::tag('p', get_string('dictionaryintro', 'local_mldict'), ['class' => 'local-mldict-dashboard-subtitle']),
        'local-mldict-hero-copy'
    ) .
    html_writer::tag('ul',
        html_writer::tag('li', get_string('fastlookup', 'local_mldict')) .
        html_writer::tag('li', get_string('languageaware', 'local_mldict')) .
        html_writer::tag('li', get_string('examplesfirst', 'local_mldict')),
        ['class' => 'local-mldict-hero-points']
    ),
    'local-mldict-hero'
);
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $url->out(false),
    'class' => 'local-mldict-search',
    'data-search-url' => (new moodle_url('/local/mldict/ajax.php'))->out(false),
]);
echo html_writer::label(get_string('searchdictionary', 'local_mldict'), 'local-mldict-q', false, ['class' => 'accesshide']);
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'name' => 'q',
    'id' => 'local-mldict-q',
    'value' => s($q),
    'placeholder' => get_string('typeword', 'local_mldict'),
    'autocomplete' => 'off',
]);
echo html_writer::select($langoptions, 'lang', $lang, false, ['id' => 'local-mldict-lang']);
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo html_writer::div('', 'local-mldict-candidates', ['aria-live' => 'polite']);
echo html_writer::div(
    html_writer::tag('button', '', [
        'type' => 'button',
        'class' => 'local-mldict-detail-backdrop',
        'aria-label' => get_string('closebuttontitle'),
    ]) .
    html_writer::tag('article',
        html_writer::tag('button', '&times;', [
            'type' => 'button',
            'class' => 'local-mldict-detail-close',
            'aria-label' => get_string('closebuttontitle'),
        ]) .
        html_writer::div('', 'local-mldict-detail-content'),
        [
            'class' => 'local-mldict-detail-panel',
            'role' => 'dialog',
            'aria-modal' => 'true',
            'aria-label' => get_string('dictionary', 'local_mldict'),
        ]
    ),
    'local-mldict-detail-popup',
    ['hidden' => 'hidden']
);

$coveragecounts = dictionary::get_language_counts();
$coverageitems = '';
foreach (dictionary::lang_options() as $code => $label) {
    $count = $coveragecounts[$code] ?? 0;
    if (($count === 0) && $code === 'zh' && array_key_exists('zh_cn', $coveragecounts)) {
        $count = $coveragecounts['zh_cn'];
    }
    $coverageitems .= html_writer::tag('li',
        html_writer::span(s($label), 'local-mldict-starter-label') .
        html_writer::span(number_format((int)$count), 'local-mldict-starter-count')
    );
}

$worditems = '';
foreach (dictionary::get_startup_starter_words($lang, 12) as $entry) {
    $worditems .= html_writer::tag('li',
        html_writer::link(
            new moodle_url('/local/mldict/view.php', ['id' => $entry->id]),
            s($entry->headword) . html_writer::span(s(dictionary::lang_label($entry->sourcelang)), 'local-mldict-starter-word-lang'),
            ['class' => 'local-mldict-starter-word']
        )
    );
}

$themegroups = [
    get_string('starterthemeclassroom', 'local_mldict') => ['school', 'book', 'study'],
    get_string('starterthemedailylife', 'local_mldict') => ['family', 'water', 'food'],
    get_string('starterthemecommunication', 'local_mldict') => ['friend', 'music', 'language'],
];
$themeitems = '';
foreach ($themegroups as $theme => $words) {
    $themeitems .= html_writer::tag('li',
        html_writer::span(s($theme), 'local-mldict-starter-theme-title') .
        html_writer::span(s(implode(' · ', $words)), 'local-mldict-starter-theme-words')
    );
}

echo html_writer::div(
    html_writer::div(
        html_writer::tag('h2', get_string('startercoverage', 'local_mldict')) .
        html_writer::tag('ul', $coverageitems, ['class' => 'local-mldict-starter-coverage']),
        'local-mldict-starter-section'
    ) .
    html_writer::div(
        html_writer::tag('h2', get_string('starterwords', 'local_mldict')) .
        html_writer::tag('ul', $worditems, ['class' => 'local-mldict-starter-words']),
        'local-mldict-starter-section'
    ) .
    html_writer::div(
        html_writer::tag('h2', get_string('starterthemes', 'local_mldict')) .
        html_writer::tag('ul', $themeitems, ['class' => 'local-mldict-starter-themes']),
        'local-mldict-starter-section local-mldict-starter-section-wide'
    ),
    'local-mldict-starter'
);

$jsstrings = json_encode([
    'definition' => get_string('definition', 'local_mldict'),
    'translations' => get_string('translations', 'local_mldict'),
    'examples' => get_string('examples', 'local_mldict'),
    'pronunciation' => get_string('pronunciation', 'local_mldict'),
], JSON_UNESCAPED_SLASHES);

echo html_writer::script("
(function() {
    var form = document.querySelector('.local-mldict-search');
    var candidates = document.querySelector('.local-mldict-candidates');
    if (!form || !candidates) {
        return;
    }

    var input = form.querySelector('input[name=\"q\"]');
    var lang = form.querySelector('select[name=\"lang\"]');
    var searchUrl = form.getAttribute('data-search-url');
    var starter = document.querySelector('.local-mldict-starter');
    var detailPopup = document.querySelector('.local-mldict-detail-popup');
    var detailContent = detailPopup ? detailPopup.querySelector('.local-mldict-detail-content') : null;
    var detailClose = detailPopup ? detailPopup.querySelector('.local-mldict-detail-close') : null;
    var detailBackdrop = detailPopup ? detailPopup.querySelector('.local-mldict-detail-backdrop') : null;
    var strings = {$jsstrings};
    var lastEntries = [];
    var timer = null;
    var controller = null;

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>'\"]/g, function(ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', \"'\": '&#039;', '\"': '&quot;'}[ch];
        });
    }

    function renderEntryDetail(entry) {
        var meta = [entry.sourceLang, entry.partOfSpeech, entry.cefrLevel].filter(Boolean).map(escapeHtml).join(' · ');
        var pron = [entry.pronunciation, entry.phonetic].filter(Boolean).map(escapeHtml).join(' ');
        var translations = (entry.translations || []).map(function(item) {
            return '<li><strong>' + escapeHtml(item.lang) + '</strong><span>' + escapeHtml(item.translation) + '</span></li>';
        }).join('');
        var examples = (entry.examples || []).map(function(item) {
            return '<li><span>' + escapeHtml(item.lang) + '</span><p>' + escapeHtml(item.sentence) + '</p>' +
                (item.translation ? '<small>' + escapeHtml(item.translation) + '</small>' : '') + '</li>';
        }).join('');

        return '<header class=\"local-mldict-popup-header\">' +
                '<h2>' + escapeHtml(entry.headword) + '</h2>' +
                (meta ? '<p>' + meta + '</p>' : '') +
            '</header>' +
            (pron ? '<section class=\"local-mldict-popup-block\"><h3>' + escapeHtml(strings.pronunciation) + '</h3><p>' + pron + '</p></section>' : '') +
            (entry.definition ? '<section class=\"local-mldict-popup-block\"><h3>' + escapeHtml(strings.definition) + '</h3><p>' + escapeHtml(entry.definition) + '</p></section>' : '') +
            (translations ? '<section class=\"local-mldict-popup-block\"><h3>' + escapeHtml(strings.translations) + '</h3><ul class=\"local-mldict-popup-translations\">' + translations + '</ul></section>' : '') +
            (examples ? '<section class=\"local-mldict-popup-block\"><h3>' + escapeHtml(strings.examples) + '</h3><ul class=\"local-mldict-popup-examples\">' + examples + '</ul></section>' : '');
    }

    function showEntryDetail(entry) {
        if (!entry || !detailPopup || !detailContent) {
            return;
        }
        detailContent.innerHTML = renderEntryDetail(entry);
        detailPopup.hidden = false;
        document.body.classList.add('local-mldict-detail-open');
        if (detailClose) {
            detailClose.focus();
        }
    }

    function hideEntryDetail() {
        if (!detailPopup) {
            return;
        }
        detailPopup.hidden = true;
        document.body.classList.remove('local-mldict-detail-open');
    }

    function closeAfterClickEffect(control, callback) {
        if (!control) {
            callback();
            return;
        }
        control.classList.add('is-clicked');
        window.setTimeout(function() {
            control.classList.remove('is-clicked');
            callback();
        }, 130);
    }

    function render(entries) {
        if (!entries.length) {
            lastEntries = [];
            candidates.innerHTML = '<div class=\"local-mldict-candidate-empty\">" . s(get_string('noentries', 'local_mldict')) . "</div>';
            return;
        }

        lastEntries = entries.slice();
        candidates.innerHTML = '<ul class=\"local-mldict-candidate-list\">' + entries.map(function(entry, index) {
            var meta = [entry.sourceLang, entry.partOfSpeech].filter(Boolean).map(escapeHtml).join(' · ');
            return '<li><a href=\"' + escapeHtml(entry.url) + '\" data-entry-index=\"' + index + '\">' +
                '<span class=\"local-mldict-candidate-word\">' + escapeHtml(entry.headword) + '</span>' +
                (meta ? '<span class=\"local-mldict-candidate-meta\">' + meta + '</span>' : '') +
            '</a></li>';
        }).join('') + '</ul>';
    }

    function fetchCandidates(openFirst) {
        var query = input.value.trim();
        if (query.length < 1) {
            candidates.innerHTML = '';
            if (starter) {
                starter.hidden = false;
            }
            return;
        }
        if (starter) {
            starter.hidden = true;
        }

        if (controller) {
            controller.abort();
        }
        controller = new AbortController();
        candidates.innerHTML = '<div class=\"local-mldict-candidate-empty\">" . s(get_string('searching', 'local_mldict')) . "</div>';

        var url = searchUrl + '?q=' + encodeURIComponent(query) + '&lang=' + encodeURIComponent(lang.value);
        fetch(url, {credentials: 'same-origin', signal: controller.signal})
            .then(function(response) { return response.ok ? response.json() : []; })
            .then(function(entries) {
                if (openFirst && entries.length) {
                    lastEntries = entries.slice();
                    showEntryDetail(entries[0]);
                    return;
                }
                render(entries);
            })
            .catch(function(error) {
                if (error.name !== 'AbortError') {
                    candidates.innerHTML = '<div class=\"local-mldict-candidate-empty\">" . s(get_string('noentries', 'local_mldict')) . "</div>';
                }
            });
    }

    function scheduleCandidates() {
        window.clearTimeout(timer);
        timer = window.setTimeout(function() { fetchCandidates(false); }, 180);
    }

    function reloadForLanguage(code) {
        var target = new URL(window.location.href);
        if (code) {
            target.searchParams.set('lang', code);
        } else {
            target.searchParams.delete('lang');
        }
        target.searchParams.delete('page');
        window.location.href = target.toString();
    }

    input.addEventListener('input', scheduleCandidates);
    lang.addEventListener('change', function() {
        if (!input.value.trim()) {
            reloadForLanguage(lang.value);
            return;
        }
        fetchCandidates(false);
    });
    document.addEventListener('flw:learningLanguageChanged', function(event) {
        var code = event.detail && event.detail.code ? event.detail.code : '';
        if (!code || lang.value === code) {
            return;
        }
        lang.value = code;
        reloadForLanguage(code);
    });
    candidates.addEventListener('click', function(event) {
        var link = event.target.closest ? event.target.closest('a[data-entry-index]') : null;
        if (!link) {
            return;
        }
        event.preventDefault();
        showEntryDetail(lastEntries[Number(link.getAttribute('data-entry-index'))]);
    });
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        fetchCandidates(true);
    });
    if (detailClose) {
        detailClose.addEventListener('click', function() {
            closeAfterClickEffect(detailClose, hideEntryDetail);
        });
    }
    if (detailBackdrop) {
        detailBackdrop.addEventListener('click', hideEntryDetail);
    }
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && detailPopup && !detailPopup.hidden) {
            hideEntryDetail();
        }
    });

    if (input.value.trim()) {
        fetchCandidates(false);
    }
}());
");

echo html_writer::end_div();
echo $output->footer();
