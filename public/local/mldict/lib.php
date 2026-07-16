<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Renders the floating FLW dictionary launcher.
 *
 * @return string HTML injected before the standard footer.
 */
function local_mldict_render_floating_dictionary(): string {
    global $PAGE;

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    $context = context_system::instance();
    if (!has_capability('local/mldict:view', $context)) {
        return '';
    }

    $searchurl = (new moodle_url('/local/mldict/ajax.php'))->out(false);
    $dictionaryurl = (new moodle_url('/local/mldict/index.php'))->out(false);
    $strings = [
        'searching' => get_string('searching', 'local_mldict'),
        'noentries' => get_string('noentries', 'local_mldict'),
        'viewentry' => get_string('viewentry', 'local_mldict'),
        'definition' => get_string('definition', 'local_mldict'),
        'translations' => get_string('translations', 'local_mldict'),
        'examples' => get_string('examples', 'local_mldict'),
        'pronunciation' => get_string('pronunciation', 'local_mldict'),
        'search' => get_string('search'),
    ];

    $selectedlang = \local_mldict\local\dictionary::preferred_learning_language();
    $languages = ['' => get_string('alllanguages', 'local_mldict')] + \local_mldict\local\dictionary::lang_options();
    $languageoptions = '';
    foreach ($languages as $code => $label) {
        $attributes = ['value' => $code];
        if ($code !== '' && $code === $selectedlang) {
            $attributes['selected'] = 'selected';
        }
        $languageoptions .= html_writer::tag('option', s($label), $attributes);
    }

    $rootclasses = ['local-mldict-floating'];
    if (!empty($PAGE->theme->name) && $PAGE->theme->name === 'flwacademy') {
        $rootclasses[] = 'local-mldict-floating-integrated';
    }

    $html = html_writer::div(
        html_writer::tag('button',
            html_writer::span('FLW', 'local-mldict-float-mark') .
            html_writer::span(get_string('dictionary', 'local_mldict'), 'local-mldict-float-text'),
            [
                'type' => 'button',
                'class' => 'local-mldict-float-button',
                'aria-expanded' => 'false',
                'aria-controls' => 'local-mldict-floating-panel',
            ]
        ) .
        html_writer::div(
            html_writer::div(
                html_writer::tag('h2', get_string('dictionary', 'local_mldict')) .
                html_writer::tag('button', '&times;', [
                    'type' => 'button',
                    'class' => 'local-mldict-floating-close',
                    'aria-label' => get_string('closebuttontitle'),
                ]),
                'local-mldict-floating-header'
            ) .
            html_writer::div(
                html_writer::empty_tag('input', [
                    'type' => 'search',
                    'class' => 'local-mldict-floating-query',
                    'placeholder' => get_string('typeword', 'local_mldict'),
                    'autocomplete' => 'off',
                ]) .
                html_writer::tag('select', $languageoptions, [
                    'class' => 'local-mldict-floating-lang',
                    'aria-label' => get_string('sourcelang', 'local_mldict'),
                ]) .
                html_writer::tag('button', get_string('search'), [
                    'type' => 'button',
                    'class' => 'local-mldict-floating-search-button',
                ]),
                'local-mldict-floating-search'
            ) .
            html_writer::div('', 'local-mldict-floating-results'),
            '',
            [
                'id' => 'local-mldict-floating-panel',
                'class' => 'local-mldict-floating-panel',
                'hidden' => 'hidden',
            ]
        ),
        implode(' ', $rootclasses)
    );
    $html .= html_writer::div(
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

    $jsconfig = json_encode([
        'searchUrl' => $searchurl,
        'dictionaryUrl' => $dictionaryurl,
        'strings' => $strings,
    ]);

    $html .= html_writer::script("
(function() {
    if (window.localMldictFloatingReady) {
        return;
    }
    window.localMldictFloatingReady = true;

    var config = {$jsconfig};
    var root = document.querySelector('.local-mldict-floating');
    if (!root) {
        return;
    }

    var button = root.querySelector('.local-mldict-float-button');
    var panel = root.querySelector('.local-mldict-floating-panel');
    var close = root.querySelector('.local-mldict-floating-close');
    var input = root.querySelector('.local-mldict-floating-query');
    var lang = root.querySelector('.local-mldict-floating-lang');
    var searchButton = root.querySelector('.local-mldict-floating-search-button');
    var results = root.querySelector('.local-mldict-floating-results');
    var detailPopup = document.querySelector('.local-mldict-detail-popup');
    var detailContent = detailPopup ? detailPopup.querySelector('.local-mldict-detail-content') : null;
    var detailClose = detailPopup ? detailPopup.querySelector('.local-mldict-detail-close') : null;
    var detailBackdrop = detailPopup ? detailPopup.querySelector('.local-mldict-detail-backdrop') : null;
    var lastEntries = [];
    var timer = null;
    var controller = null;
    var positionKey = 'localMldictFloatingPosition';
    var drag = null;
    var suppressClick = false;

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
            (pron ? '<section class=\"local-mldict-popup-block\"><h3>' + escapeHtml(config.strings.pronunciation) + '</h3><p>' + pron + '</p></section>' : '') +
            (entry.definition ? '<section class=\"local-mldict-popup-block\"><h3>' + escapeHtml(config.strings.definition) + '</h3><p>' + escapeHtml(entry.definition) + '</p></section>' : '') +
            (translations ? '<section class=\"local-mldict-popup-block\"><h3>' + escapeHtml(config.strings.translations) + '</h3><ul class=\"local-mldict-popup-translations\">' + translations + '</ul></section>' : '') +
            (examples ? '<section class=\"local-mldict-popup-block\"><h3>' + escapeHtml(config.strings.examples) + '</h3><ul class=\"local-mldict-popup-examples\">' + examples + '</ul></section>' : '');
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

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function applyPosition(position) {
        if (!position || typeof position.left !== 'number' || typeof position.top !== 'number') {
            return;
        }
        var rect = root.getBoundingClientRect();
        var left = clamp(position.left, 8, Math.max(8, window.innerWidth - rect.width - 8));
        var top = clamp(position.top, 8, Math.max(8, window.innerHeight - rect.height - 8));
        root.style.left = left + 'px';
        root.style.top = top + 'px';
        root.style.right = 'auto';
        root.style.bottom = 'auto';
    }

    function loadSavedPosition() {
        try {
            applyPosition(JSON.parse(window.localStorage.getItem(positionKey) || 'null'));
        } catch (error) {
            window.localStorage.removeItem(positionKey);
        }
    }

    function savePosition() {
        try {
            var rect = root.getBoundingClientRect();
            window.localStorage.setItem(positionKey, JSON.stringify({left: rect.left, top: rect.top}));
        } catch (error) {
            // Ignore storage failures, such as private browsing restrictions.
        }
    }

    function startDrag(event) {
        if (event.button !== undefined && event.button !== 0) {
            return;
        }
        var rect = root.getBoundingClientRect();
        drag = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            left: rect.left,
            top: rect.top,
            moved: false
        };
        if (button.setPointerCapture) {
            button.setPointerCapture(event.pointerId);
        }
    }

    function moveDrag(event) {
        if (!drag || event.pointerId !== drag.pointerId) {
            return;
        }
        var dx = event.clientX - drag.startX;
        var dy = event.clientY - drag.startY;
        if (!drag.moved && Math.sqrt((dx * dx) + (dy * dy)) < 5) {
            return;
        }
        drag.moved = true;
        suppressClick = true;
        root.classList.add('local-mldict-floating-dragging');
        event.preventDefault();
        applyPosition({left: drag.left + dx, top: drag.top + dy});
    }

    function endDrag(event) {
        if (!drag || event.pointerId !== drag.pointerId) {
            return;
        }
        if (button.releasePointerCapture) {
            button.releasePointerCapture(event.pointerId);
        }
        if (drag.moved) {
            savePosition();
            window.setTimeout(function() {
                suppressClick = false;
            }, 0);
        }
        root.classList.remove('local-mldict-floating-dragging');
        drag = null;
    }

    function setOpen(open) {
        panel.hidden = !open;
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        root.classList.toggle('local-mldict-floating-open', open);
        if (open) {
            window.setTimeout(function() { input.focus(); }, 0);
        }
    }

    function renderEntries(entries) {
        if (!entries.length) {
            lastEntries = [];
            results.innerHTML = '<div class=\"local-mldict-floating-empty\">' + escapeHtml(config.strings.noentries) + '</div>';
            return;
        }

        lastEntries = entries.slice();
        results.innerHTML = '<ul class=\"local-mldict-floating-candidates\">' + entries.map(function(entry, index) {
            var meta = [entry.sourceLang, entry.partOfSpeech, entry.cefrLevel].filter(Boolean).map(escapeHtml).join(' · ');
            return '<li><a href=\"' + escapeHtml(entry.url) + '\" data-entry-index=\"' + index + '\">' +
                '<span class=\"local-mldict-floating-word\">' + escapeHtml(entry.headword) + '</span>' +
                (meta ? '<span class=\"local-mldict-floating-meta\">' + meta + '</span>' : '') +
            '</a></li>';
        }).join('') + '</ul>';
    }

    function search(openFirst) {
        var query = input.value.trim();
        if (query.length < 1) {
            results.innerHTML = '';
            return;
        }

        if (controller) {
            controller.abort();
        }
        controller = new AbortController();
        results.innerHTML = '<div class=\"local-mldict-floating-empty\">' + escapeHtml(config.strings.searching) + '</div>';

        var url = config.searchUrl + '?q=' + encodeURIComponent(query) + '&lang=' + encodeURIComponent(lang.value);
        fetch(url, {credentials: 'same-origin', signal: controller.signal})
            .then(function(response) { return response.ok ? response.json() : []; })
            .then(function(entries) {
                if (openFirst && entries.length) {
                    lastEntries = entries.slice();
                    showEntryDetail(entries[0]);
                    return;
                }
                renderEntries(entries);
            })
            .catch(function(error) {
                if (error.name !== 'AbortError') {
                    results.innerHTML = '<div class=\"local-mldict-floating-empty\">' + escapeHtml(config.strings.noentries) + '</div>';
                }
            });
    }

    function scheduleSearch() {
        window.clearTimeout(timer);
        timer = window.setTimeout(function() { search(false); }, 220);
    }

    loadSavedPosition();
    window.addEventListener('resize', function() {
        var rect = root.getBoundingClientRect();
        if (root.style.left || root.style.top) {
            applyPosition({left: rect.left, top: rect.top});
            savePosition();
        }
    });

    button.addEventListener('pointerdown', startDrag);
    button.addEventListener('pointermove', moveDrag);
    button.addEventListener('pointerup', endDrag);
    button.addEventListener('pointercancel', endDrag);
    button.addEventListener('click', function(event) {
        if (suppressClick) {
            event.preventDefault();
            event.stopPropagation();
            return;
        }
        setOpen(panel.hidden);
    });
    close.addEventListener('click', function() {
        closeAfterClickEffect(close, function() { setOpen(false); });
    });
    input.addEventListener('input', scheduleSearch);
    lang.addEventListener('change', function() { search(false); });
    searchButton.addEventListener('click', function() { search(true); });
    results.addEventListener('click', function(event) {
        var link = event.target.closest ? event.target.closest('a[data-entry-index]') : null;
        if (!link) {
            return;
        }
        event.preventDefault();
        showEntryDetail(lastEntries[Number(link.getAttribute('data-entry-index'))]);
    });
    input.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            search(true);
        }
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
        }
        if (event.key === 'Escape' && detailPopup && !detailPopup.hidden) {
            hideEntryDetail();
        }
    });
    if (detailClose) {
        detailClose.addEventListener('click', function() {
            closeAfterClickEffect(detailClose, hideEntryDetail);
        });
    }
    if (detailBackdrop) {
        detailBackdrop.addEventListener('click', hideEntryDetail);
    }
}());
");

    return $html;
}

/**
 * Compatibility wrapper for older local plugin callback names.
 *
 * @return string
 */
function local_mldict_before_standard_footer_html(): string {
    return local_mldict_render_floating_dictionary();
}
