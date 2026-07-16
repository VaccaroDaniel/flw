// This file is part of Moodle - http://moodle.org/

define([
    'core/ajax',
    'core/templates',
    'local_flwmedia/player',
    'local_flwmedia/recorder',
    'local_flwmedia/reader',
    'local_flwmedia/dictation',
    'local_flwmedia/lazyload'
], function(Ajax, Templates, Player, Recorder, Reader, Dictation, Lazyload) {
    var modes = [
        {key: 'watch', label: 'Watch', symbol: 'W', meta: 'Video'},
        {key: 'listen', label: 'Listen', symbol: 'L', meta: 'Audio'},
        {key: 'speak', label: 'Speak', symbol: 'S', meta: 'Record'},
        {key: 'read', label: 'Read', symbol: 'Aa', meta: 'Text'},
        {key: 'dictate', label: 'Dictate', symbol: 'D', meta: 'Type'}
    ];

    var categories = [
        {key: 'all', label: 'All'}
    ];

    var templateForMode = {
        watch: 'local_flwmedia/card_video',
        listen: 'local_flwmedia/card_audio',
        speak: 'local_flwmedia/card_speak',
        read: 'local_flwmedia/card_read',
        dictate: 'local_flwmedia/card_dictate'
    };

    /**
     * Hub controller.
     *
     * @param {Element} root Root hub element.
     */
    function Hub(root) {
        this.root = root;
        this.language = root.dataset.language || root.dataset.lang || 'en';
        this.courseid = parseInt(root.dataset.courseid || '0', 10);
        this.courseid = isNaN(this.courseid) ? 0 : this.courseid;
        this.unitcode = root.dataset.unitcode || '';
        this.mode = root.dataset.defaultmode || 'watch';
        this.category = 'all';
        this.query = '';
        this.page = 1;
        this.perpage = parseInt(root.dataset.perpage || '12', 10);
        this.totalPages = 1;
        this.totalItems = 0;
        this.categories = categories;
    }

    Hub.prototype.init = function() {
        this.renderShell();
        this.bindShell();
        return this.loadItems();
    };

    Hub.prototype.renderShell = function() {
        var modeTiles = modes.map(function(mode) {
            return [
                '<button type="button" class="flwmedia-mode-card" data-mode="' + mode.key + '">',
                '<span class="flwmedia-mode-symbol">' + mode.symbol + '</span>',
                '<span class="flwmedia-mode-copy">',
                '<strong>' + mode.label + '</strong>',
                '<small>' + mode.meta + '</small>',
                '</span>',
                '</button>'
            ].join('');
        }).join('');
        var categoryButtons = this.renderCategoryButtons();

        this.root.innerHTML = [
            '<div class="flwmedia-shell">',
            '<header class="flwmedia-header">',
            '<div>',
            '<div class="flwmedia-kicker">FLW Practice</div>',
            '<h3>Practice</h3>',
            '<div class="flwmedia-unit">' + this.escapeHtml(this.getContextLabel()) + '</div>',
            '</div>',
            '<div class="flwmedia-summary">',
            '<span class="flwmedia-summary-number">0</span>',
            '<span class="flwmedia-summary-label">items</span>',
            '</div>',
            '</header>',
            '<div class="flwmedia-mode-grid" role="tablist">' + modeTiles + '</div>',
            '<section class="flwmedia-workspace">',
            '<div class="flwmedia-toolbar">',
            '<div class="flwmedia-current-mode" aria-live="polite"></div>',
            '<input class="flwmedia-search" type="search" placeholder="Search practice" aria-label="Search FLW media">',
            '</div>',
            '<div class="flwmedia-filters"><div class="flwmedia-categories">' + categoryButtons + '</div></div>',
            '<div class="flwmedia-grid" aria-live="polite"></div>',
            '<div class="flwmedia-pager-wrap"></div>',
            '</section>',
            '</div>'
        ].join('');
        this.syncActiveControls();
    };

    Hub.prototype.bindShell = function() {
        var self = this;
        var debounceTimer = null;

        this.root.addEventListener('click', function(event) {
            var modeButton = event.target.closest('.flwmedia-mode-card');
            if (modeButton) {
                self.switchMode(modeButton.dataset.mode);
                return;
            }

            var categoryButton = event.target.closest('.flwmedia-category');
            if (categoryButton) {
                self.switchCategory(categoryButton.dataset.category);
                return;
            }

            var pageButton = event.target.closest('.flwmedia-page-number');
            if (pageButton) {
                self.page = parseInt(pageButton.dataset.page, 10);
                self.loadItems();
                return;
            }

            if (event.target.closest('.flwmedia-page-prev')) {
                self.previousPage();
                return;
            }

            if (event.target.closest('.flwmedia-page-next')) {
                self.nextPage();
            }
        });

        this.root.querySelector('.flwmedia-search').addEventListener('input', function(event) {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(function() {
                self.search(event.target.value);
            }, 250);
        });
    };

    Hub.prototype.syncActiveControls = function() {
        var activeMode = modes.filter(function(mode) {
            return mode.key === this.mode;
        }, this)[0] || modes[0];

        Array.prototype.forEach.call(this.root.querySelectorAll('.flwmedia-mode-card'), function(button) {
            button.classList.toggle('is-active', button.dataset.mode === this.mode);
        }, this);
        Array.prototype.forEach.call(this.root.querySelectorAll('.flwmedia-category'), function(button) {
            button.classList.toggle('is-active', button.dataset.category === this.category);
        }, this);

        var currentMode = this.root.querySelector('.flwmedia-current-mode');
        if (currentMode) {
            currentMode.innerHTML = [
                '<span class="flwmedia-current-symbol">' + activeMode.symbol + '</span>',
                '<span><strong>' + activeMode.label + '</strong><small>' + activeMode.meta + '</small></span>'
            ].join('');
        }
    };

    Hub.prototype.loadItems = function() {
        var self = this;
        var grid = this.root.querySelector('.flwmedia-grid');
        grid.innerHTML = '<div class="flwmedia-loading">Loading FLW practice...</div>';
        this.syncActiveControls();

        return Ajax.call([{
            methodname: 'local_flwmedia_get_items',
            args: {
                courseid: this.courseid,
                language: this.language,
                unitcode: this.unitcode,
                mode: this.mode,
                category: this.category,
                search: this.query,
                page: this.page,
                perpage: this.perpage
            }
        }])[0].then(function(result) {
            self.totalPages = result.pages || 1;
            self.totalItems = result.total || 0;
            self.updateCategories(result.categories || []);
            self.renderSummary(result);
            return self.renderItems(result.items || []).then(function() {
                return self.renderPager(result);
            });
        }).catch(function(error) {
            grid.innerHTML = '<div class="flwmedia-error">FLW media could not be loaded.</div>';
            throw error;
        });
    };

    Hub.prototype.updateCategories = function(loadedCategories) {
        var seen = {all: true};
        this.categories = [{key: 'all', label: 'All'}];
        loadedCategories.forEach(function(category) {
            if (!category.key || seen[category.key]) {
                return;
            }
            seen[category.key] = true;
            this.categories.push({
                key: category.key,
                label: category.label || category.key
            });
        }, this);

        if (!seen[this.category]) {
            this.category = 'all';
        }

        var target = this.root.querySelector('.flwmedia-categories');
        if (target) {
            target.innerHTML = this.renderCategoryButtons();
            this.syncActiveControls();
        }
    };

    Hub.prototype.renderCategoryButtons = function() {
        return this.categories.map(function(category) {
            return '<button type="button" class="flwmedia-category" data-category="' +
                this.escapeHtml(category.key) + '">' + this.escapeHtml(category.label) + '</button>';
        }, this).join('');
    };

    Hub.prototype.renderSummary = function(result) {
        var summaryNumber = this.root.querySelector('.flwmedia-summary-number');
        var summaryLabel = this.root.querySelector('.flwmedia-summary-label');
        if (summaryNumber) {
            summaryNumber.textContent = result.total || 0;
        }
        if (summaryLabel) {
            summaryLabel.textContent = (result.total === 1) ? 'item' : 'items';
        }
    };

    Hub.prototype.renderItems = function(items) {
        var self = this;
        var grid = this.root.querySelector('.flwmedia-grid');

        if (!items.length) {
            grid.innerHTML = [
                '<div class="flwmedia-empty">',
                '<strong>No practice media found</strong>',
                '<span>Try another mode, category, or search.</span>',
                '</div>'
            ].join('');
            return Promise.resolve();
        }

        return Promise.all(items.map(function(item) {
            return Templates.renderForPromise(templateForMode[self.mode], item);
        })).then(function(rendered) {
            grid.innerHTML = rendered.map(function(result) {
                return result.html || result[0] || result;
            }).join('');
            Lazyload.init(grid);
            Player.init(grid, self.saveProgress.bind(self));
            if (self.mode === 'speak') {
                Recorder.init(grid, self.saveSpeakingAttempt.bind(self));
            } else if (self.mode === 'read') {
                Reader.init(grid, self.saveReadingAttempt.bind(self));
            } else if (self.mode === 'dictate') {
                Dictation.init(grid, self.saveDictationAttempt.bind(self));
            }
        });
    };

    Hub.prototype.renderPager = function(result) {
        var self = this;
        var pages = [];
        var total = result.pages || 1;
        var start = Math.max(1, result.page - 2);
        var end = Math.min(total, start + 4);

        for (var i = start; i <= end; i++) {
            pages.push({page: i, active: i === result.page});
        }

        return Templates.renderForPromise('local_flwmedia/pager', {
            hasprevious: result.page > 1,
            hasnext: result.page < total,
            pageslist: pages
        }).then(function(html) {
            self.root.querySelector('.flwmedia-pager-wrap').innerHTML = html.html || html[0] || html;
        });
    };

    Hub.prototype.switchMode = function(mode) {
        if (this.mode === mode) {
            return;
        }
        this.mode = mode;
        this.category = 'all';
        this.page = 1;
        this.loadItems();
    };

    Hub.prototype.switchCategory = function(category) {
        if (this.category === category) {
            return;
        }
        this.category = category;
        this.page = 1;
        this.loadItems();
    };

    Hub.prototype.search = function(query) {
        this.query = query || '';
        this.page = 1;
        this.loadItems();
    };

    Hub.prototype.nextPage = function() {
        if (this.page < this.totalPages) {
            this.page++;
            this.loadItems();
        }
    };

    Hub.prototype.previousPage = function() {
        if (this.page > 1) {
            this.page--;
            this.loadItems();
        }
    };

    Hub.prototype.saveProgress = function(itemid, mode, percent, completed, score, attemptjson) {
        return Ajax.call([{
            methodname: 'local_flwmedia_save_progress',
            args: {
                courseid: this.courseid,
                language: this.language,
                itemid: parseInt(itemid, 10),
                mode: mode,
                percentdone: percent || 0,
                secondsdone: 0,
                completed: !!completed,
                score: score,
                attemptjson: attemptjson || ''
            }
        }])[0];
    };

    Hub.prototype.saveSpeakingAttempt = function(itemid, data) {
        return this.saveAttempt('local_flwmedia_save_speaking_attempt', itemid, data);
    };

    Hub.prototype.saveReadingAttempt = function(itemid, data) {
        return this.saveAttempt('local_flwmedia_save_reading_attempt', itemid, data);
    };

    Hub.prototype.saveDictationAttempt = function(itemid, data) {
        return this.saveAttempt('local_flwmedia_save_dictation_attempt', itemid, data);
    };

    Hub.prototype.saveAttempt = function(methodname, itemid, data) {
        data = data || {};
        return Ajax.call([{
            methodname: methodname,
            args: {
                courseid: this.courseid,
                language: this.language,
                itemid: parseInt(itemid, 10),
                response: data.response || '',
                transcript: data.transcript || '',
                score: data.score,
                feedback: data.feedback || '',
                audiofileurl: data.audiofileurl || '',
                attemptjson: data.attemptjson || ''
            }
        }])[0];
    };

    Hub.prototype.escapeHtml = function(value) {
        return String(value || '').replace(/[&<>"']/g, function(character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    };

    Hub.prototype.getContextLabel = function() {
        var label = this.language.toUpperCase();
        if (this.unitcode) {
            label += ' / ' + this.unitcode;
        }
        return label;
    };

    var init = function(root) {
        if (!root || root.dataset.flwmediaReady === '1') {
            return;
        }
        root.dataset.flwmediaReady = '1';
        var hub = new Hub(root);
        return hub.init();
    };

    var initAll = function() {
        Array.prototype.forEach.call(document.querySelectorAll('.flwmedia-hub'), init);
    };

    return {
        init: init,
        initAll: initAll
    };
});
