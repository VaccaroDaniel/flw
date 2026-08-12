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
 * Module for managing content designer elements and animations.
 *
 * This module defines various functions and constants to handle the loading,
 * refreshing, and animating of content designer elements within a course.
 * It utilizes organization-specific modules for fragment loading and template
 * manipulation, and includes features such as entrance animations and scrolling
 * effects for elements in the viewport.
 *
 * @module mod_contentdesigner/editor
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/fragment', 'core/templates', 'core/loadingicon',  'core/notification', 'mod_contentdesigner/anime'],
    function($, Fragment, Templates, LoadingIcon, Notification, anime) {

        /**
         * Selectors.
         */
        const SELECTORS = {
            chapter: '[data-elementshortname="chapter"]',
            chapterList: '.chapter-elements-list',
            elementContent: '.element-content',
            fragments: {
                nextContents: 'load_remain_capter_contents'
            },
            contentWrapper: '.contentdesigner-wrapper',
        };

        const detailElement = 'input[name=contentdesigner_cm_details]';

        /**
         * Get the content designer elements data.
         *
         * @param {int} cmID
         * @param {int} contextID
         * @param {int} contentdesignerID
         * @returns {object}
         */
        function contentDesignerElementsData(cmID, contextID, contentdesignerID) {
            var data = {
                cmid: cmID,
                contextid: contextID,
                contentdesignerid: contentdesignerID
            };
            return data;
        }

        const contentDesignerData = () => {
            var cmdetails = document.querySelector(detailElement) !== null ? document.querySelector(detailElement).value : '';
            var cmdata = (cmdetails) ? JSON.parse(cmdetails) : '';
            return cmdata || contentDesignerElementsData();
        };

        let animations = {};

        let button = () => document.querySelector('.contentdesigner-content');

        let courseContentSelector = 'ul.course-content-list';

        let courseContent = () => document.querySelector(courseContentSelector);

        const contentWrapper = () => document.querySelector(SELECTORS.contentWrapper);

        const refreshContent = () => {
            var params = {
                cmid: contentDesignerData().cmid
            };
            var promise = Fragment.loadFragment('mod_contentdesigner', 'load_elements', contentDesignerData().contextid, params);
            promise.done((html, js) => {
                var fakeDiv = document.createElement('div');
                fakeDiv.innerHTML = html;
                var chapters = fakeDiv.querySelector('.course-content-list').children;
                var filterChapter = [];

                chapters.forEach((chapter) => {
                    var elementSelector = 'li.element-item[data-instanceid="' + chapter.dataset.instanceid + '"]';
                    elementSelector += '[data-elementshortname="' + chapter.dataset.elementshortname + '"]';
                    let chapterSelector = 'li.chapters-list[data-id="' + chapter.dataset.id + '"]';

                    if (!document.querySelector(chapterSelector) && !document.querySelector(elementSelector)) {
                        filterChapter.push(chapter);
                    } else {
                        var elements = chapter.querySelectorAll('.element-item') ?? [];
                        elements.forEach((element) => {
                            var dataset = element.children[0].dataset;
                            var selector = 'li.element-item .element-content[data-instanceid="' + dataset.instanceid + '"]';
                            selector += '[data-elementshortname="' + dataset.elementshortname + '"]';
                            var elementindocument = document.querySelector(selector);

                            if (!document.querySelector(selector)
                                && document.querySelector(chapterSelector + ' .chapter-elements-list') !== undefined) {
                                document.querySelector(chapterSelector + ' .chapter-elements-list').appendChild(element);
                            } else if (elementindocument !== null && elementindocument.dataset.replaceonrefresh == true) {
                                Templates.replaceNode(elementindocument, element, '');
                            }
                        });
                        if (chapter.querySelector('.chapter-elements-list') !== null) {
                            chapter.querySelector('.chapter-elements-list').remove();
                        }
                        if (chapter.querySelector('.chapter-title') !== null) {
                            chapter.querySelector('.chapter-title').remove();
                        }
                        if (chapter.children.length > 0 && document.querySelector(chapterSelector) != undefined) {
                            removeMarkBtn(chapterSelector);
                            document.querySelector(chapterSelector).append(chapter.children[0]);
                        }
                    }
                });

                Templates.appendNodeContents('.contentdesigner-content .course-content-list', filterChapter, js);
                animateElements();
                contentWrapper().dispatchEvent(new CustomEvent('elementupdate')); // Dispatch the element update event.
            }
            ).catch((error) => Notification.exception(error));

            LoadingIcon.addIconToContainerRemoveOnCompletion(button(), promise);
        };

        const removeMarkBtn = (chapterSelector) => {
            if (document.querySelector(chapterSelector).querySelector('.toolbar-block') != undefined) {
                document.querySelector(chapterSelector).querySelector('.toolbar-block').remove();
            }
        };

        /**
         * Remove the warning from response.
         */
        const removeWarning = () => {
            if (courseContent() !== null && courseContent().querySelector('.label.label-warning') !== null) {
                courseContent().querySelector('.label.label-warning').remove();
            }
        };

        const loadNextChapters = function(currentChapter) {
            var params = {
                cmid: contentDesignerData().cmid,
                chapter: currentChapter
            };
            var promise = Fragment.loadFragment('mod_contentdesigner', 'load_next_chapters',
                contentDesignerData().contextid, params);
            promise.done((html, js) => {
                var fakeDiv = document.createElement('div');
                fakeDiv.innerHTML = html;
                var chapters = fakeDiv.querySelector('.course-content-list').children;
                var filterChapter = [];
                chapters.forEach((chapter) => {
                    var elementSelector = 'li.element-item[data-instanceid="' + chapter.dataset.instanceid + '"]';
                    elementSelector += '[data-elementshortname="' + chapter.dataset.elementshortname + '"]';

                    if (!document.querySelector('li.chapters-list[data-id="' + chapter.dataset.id + '"]')
                        && !document.querySelector(elementSelector)) {
                        filterChapter.push(chapter);
                    }
                });
                Templates.appendNodeContents('.contentdesigner-content .course-content-list', filterChapter, js);
                animateElements();
            }).catch((error) => Notification.exception(error));

            LoadingIcon.addIconToContainerRemoveOnCompletion(button(), promise);
        };

        const loadNextElements = function (currentElement) {

            Fragment.loadFragment(
                'mod_contentdesigner', SELECTORS.fragments.nextContents,
                contentDesignerData().contextid, {contentid: currentElement.dataset.contentid}
            ).done((html, js) => {

                const selector = currentElement.parentNode.parentNode;

                Templates.appendNodeContents(selector, html, js);
            });
        };

        /**
         * Verify the given element in the viewport.
         *
         * @param {HTMLElement} el Element to verify
         * @returns {bool}
         */
        const inView = function(el) {
            const rect = el.getBoundingClientRect();
            return (rect.top >= 0 && rect.left >= 0
                && rect.bottom <= (window.innerHeight || document.documentElement.clientHeight)
                && rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        };

        /**
         * Animate the module elements when the elements in the viewport.
         */
        const animateElements = function() {
            entranceAnimation(); // Init entrance animation.
            scrollingEffects();
        };

        /**
         * Initilaize the scroll reveal animation for elements.
         */
        function entranceAnimation() {

            var leftFrame = [
                {transform: 'translate3d(-100%, 0, 0)'},
                {transform: 'translate3d(0, 0, 0)', opacity: 1},
            ];

            var rightFrame = [
                {transform: 'translate3d(100%, 0, 0)'},
                {transform: 'translate3d(10%, 0, 0)', opacity: 1},
            ];

            var fadeIn = [
                {opacity: 0},
                {opacity: 1}
            ];

            const items = document.querySelectorAll('.element-item .general-options.animation');
            items.forEach((item) => {
                var node = item;
                if (node.dataset.entranceanimation == undefined || node.dataset.entranceanimation == '') {
                    return;
                }
                var data = JSON.parse(node.dataset.entranceanimation);
                var speed = 1500;
                if (data.duration == 'slow') {
                    speed = 3000;
                } else if (data.duration == 'fast') {
                    speed = 500;
                }
                const observer = new IntersectionObserver(entries => {
                    entries.forEach(entry => {
                        const intersecting = entry.isIntersecting;
                        if (intersecting) {
                            node = entry.target;
                            if (node.classList.contains('animated')) {
                                return;
                            }

                            var frame = fadeIn;
                            if (data.animation == 'slideInLeft') {
                                frame = leftFrame;
                            } else if (data.animation == 'slideInRight') {
                                frame = rightFrame;
                            }

                            setTimeout(function() {
                                node.classList.add('animated');
                                item.animate(frame, {duration: speed || 1000});
                            }, data.delay);
                            observer.unobserve(item);
                        }
                    });
                });

                observer.observe(item);
            });
        }

        /**
         * Setup the scrolling effects.
         */
        function scrollingEffects() {

            document.querySelectorAll('.element-item').forEach(function(item) {
                animate(item);
            });
            /* Item = document.querySelectorAll('.element-item')[5];
            animate(item); */
        }

        /**
         * Animate the element based on scrolling.
         * @param {HTMLELement} item
         * @returns {void}
         */
        function animate(item) {

            var node = item.childNodes[1];
            if (node.dataset.scrolleffect == undefined || node.dataset.scrolleffect == '') {
                return;
            }
            var id = node.dataset.contentid;
            var data = JSON.parse(node.dataset.scrolleffect);
            var speed = data.speed ? (10000 / data.speed) : 0;
            animations[id] = anime({
                targets: item.childNodes[1],
                translateX: data.direction == 'left' ? [800, 0] : [-800, 0],
                duration: speed || 500,
                autoplay: false,
                elasticity: 0,
                easing: 'easeInOutSine'
            });

            document.getElementById('page').addEventListener('scroll', function() {
                document.getElementById('page').style.overflow = 'visible';
                var scroll = animateOnScroll(item, animations[id].duration);
                animations[id].seek(scroll);
            });
            window.addEventListener('scroll', function() {
                var scroll = animateOnScroll(item, animations[id].duration);
                animations[id].seek(scroll);
            });
            var modalBody = document.querySelector('.path-course-view .modal-dialog-scrollable .modal-body');
            if (modalBody !== null) {
                modalBody.addEventListener('scroll', function(e) {
                    var scroll = animateOnScroll(item, animations[id].duration, e.target);
                    animations[id].seek(scroll);
                });
            }

            window.addEventListener('load', function() {
                setTimeout(function() {
                    document.getElementById('page').style.overflow = 'visible';
                }, 500);
                var scroll = animateOnScroll(item, animations[id].duration);
                animations[id].seek(scroll);
            });

            document.getElementById('page').style.overflow = 'visible';
        }

        /**
         * Find the seek volume for the given element to move the element.
         *
         * @param {HTMLElement} item
         * @param {double} dataspeed
         * @param {HTMLElement} scrollElement
         * @returns {bool}
         */
        const animateOnScroll = function(item, dataspeed, scrollElement = null) {

            var node = item.childNodes[1];
            if (node.dataset.scrolleffect == undefined || node.dataset.scrolleffect == '') {
                return;
            }
            var data = JSON.parse(node.dataset.scrolleffect);
            let start = parseInt(data.start) || 100;
            let end = 200;
            const docElement = scrollElement || document.documentElement;
            let clientHeight = docElement.clientHeight;
            let itemY = () => item.getBoundingClientRect().y;
            let itemTop = () => item.getBoundingClientRect().top - end;
            let isStartPosition = () => itemY() + start <= (docElement.clientHeight);
            let isEndPosition = () => itemY() <= end;
            if (isStartPosition() && !isEndPosition()) {
                var ls = docElement.scrollHeight - (docElement.clientHeight + docElement.scrollTop);
                var availablescroll = Math.min(clientHeight - (end + start), ls);
                var scrolled = (availablescroll > itemTop())
                    ? availablescroll - itemTop() : (item.offsetTop - end - itemTop()) - availablescroll;
                var percent = dataspeed / availablescroll;
                var speed = scrolled * percent;
                return speed;
            }
            return;
        };

        return {
            data: contentDesignerData, // Backward compatibility.
            contentDesignerData: contentDesignerData,
            refreshContent: refreshContent,
            loadNextChapters: loadNextChapters,
            inView: inView,
            animateElements: animateElements,
            courseContent: courseContent,
            removeWarning: removeWarning,
            loadNextElements: loadNextElements,
            contentDesignerElementsData: contentDesignerElementsData,
            selectors: SELECTORS
        };
    });
