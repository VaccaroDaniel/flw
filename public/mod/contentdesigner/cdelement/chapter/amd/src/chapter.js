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
 * Initializes event listeners and manages chapter completion and progress updates
 * for the content designer module. Handles user interactions such as completing
 * chapters and updating the progress bar. Supports both standard and popup formats.
 * Utilizes AJAX calls to update chapter completion status and refreshes content
 * accordingly. Ensures sticky progress bar behavior during scrolling.
 *
 * @module cdelement_chapter/chapter
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'mod_contentdesigner/elements', 'core/ajax', 'core/fragment',
    'core/templates', 'core/loadingicon', 'core/notification', 'core/str'],
    function($, Elements, AJAX, Fragment, Templates, LoadingIcon, Notification, Str) {

        const chapterCTA = 'button.complete-chapter';

        const progressBar = 'div#contentdesigner-progressbar';

        let completionIcon, completionStr;

        const initEventListeners = () => {
            Templates.renderPix('e/tick', 'core').done(function(img) {
                completionIcon = img;
            });
            Str.get_string('completion_manual:done', 'course').done((str) => {
                completionStr = str;
            });
            // Remove previous eventlisteners on body. to support popup format.
            document.body.removeEventListener('click', completeChapterListener);
            document.body.addEventListener('click', completeChapterListener);

            document.querySelector('#page').addEventListener('scroll', () => {
                stickyProgress();
            });

            // Popup format support.
            var popup = document.querySelector('body.format-popups .modal-content .modal-body');
            if (popup !== null) {
                popup.addEventListener('scroll', () => {
                    stickyProgress();
                });
            }

            window.addEventListener('scroll', () => {
                stickyProgress();
            });
        };

        const completeChapterListener = (e) => {
            var completeCTA = e.target.closest(chapterCTA);
            if (completeCTA != undefined) {
                e.preventDefault();
                var chapter = completeCTA.dataset.chapterid;
                var promise = completeChapter(chapter, completeCTA);
                promise.done(() => {
                    updateProgress();
                    completeCTA.classList.remove('btn-outline-secondary');
                    completeCTA.classList.add('btn-success');
                    completeCTA.innerHTML = completionIcon + ' ' + completionStr;
                    Elements.removeWarning();
                    Elements.refreshContent();
                    let chapterlist = e.target.closest('.chapters-list');

                    if (chapterlist && !chapterlist.classList.contains('completed')) {
                        chapterlist.classList.add('completed');
                    }
                    // TODO: Add a additional function to support loadnext chapter works like replaceonrefresh.
                    // Until hide this loadNextchapters().
                    // Elements.loadNextChapters(chapter);
                }).catch(Notification.exception);
            }
        };

        const stickyProgress = function () {
            var progressElem = document.querySelector('.contentdesigner-progress');
            var contentWrapper = document.querySelector('.contentdesigner-content');
            if (contentWrapper != undefined && contentWrapper.getBoundingClientRect().top < 50) {
                contentWrapper.classList.add('sticky-progress');
                progressElem.classList.add('fixed-top');
            } else {
                progressElem.classList.remove('fixed-top');
                contentWrapper.classList.remove('sticky-progress');
            }
        };

        const completeChapter = (chapter, button) => {
            var promises = AJAX.call([{
                methodname: 'cdelement_chapter_update_completion',
                args: {
                    chapter: chapter,
                    cmid: Elements.contentDesignerData().cmid
                }
            }]);
            LoadingIcon.addIconToContainerRemoveOnCompletion(button, promises[0]);

            return promises[0];
        };

        const updateProgress = () => {
            var params = {cmid: Elements.contentDesignerData().cmid};
            Fragment.loadFragment('cdelement_chapter', 'update_progressbar',
                Elements.contentDesignerData().contextid, params).done((html, js) => {
                    Templates.replaceNode(progressBar, html, js);
                }).catch(Notification.exception);
        };

        return {
            init: function() {
                initEventListeners();
            },
        };
    });
