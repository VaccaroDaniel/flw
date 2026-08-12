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
 * Provides actions for the content designer editor, including update, edit, and move element functionalities.
 *
 * @module mod_contentdesigner/editor
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/str',
    'core/fragment', 'core/templates', 'core/notification', 'core/loadingicon', 'core/modal'],
    function($, ModalFactory, ModalEvents, Str, Fragment, Templates, Notification, LoadingIcon, Modal) {
        var contextID;

        var cmID;

        let loaderItem = '.contentdesigner-content';

        const editor = (contextid, cmid) => {
            if (document.body.id !== 'page-mod-contentdesigner-editor') {
                return null;
            }
            contextID = contextid;
            cmID = cmid;
            initEventListeners();
            return null;
        };

        const initEventListeners = () => {
            document.body.addEventListener('click', (e) => {
                var addElement = e.target.closest('.contentdesigner-addelement');
                var elementAction = e.target.closest(".element-item .element-actions .action-item");
                var moduleElement = e.target.closest("div.element-item");
                if (elementAction && elementAction != undefined
                    && moduleElement && moduleElement != undefined) {
                    var action = elementAction.getAttribute('data-action');
                    var elementId = moduleElement.getAttribute('data-elementid');
                    var element = moduleElement.getAttribute('data-elementshortname');
                    var instanceId = moduleElement.getAttribute('data-instanceid');
                    if (action === 'delete') {
                        // Deleting requires confirmation.
                        confirmDeleteElement(element, function() {
                            editElement(moduleElement, elementId, instanceId, action);
                        });
                    }

                    if (action == 'moveup' || action == 'movedown') {
                        moveElement(moduleElement, action);
                    }

                    if (action == 'status') {
                        updateStatus(moduleElement);
                    }
                }

                if (addElement && addElement != undefined) {
                    e.preventDefault();
                    var position = addElement.dataset.position;
                    var chapter = addElement.dataset.chapter;
                    buildAddElementModal(position, chapter);
                }

            });
        };


        const moveElement = (moduleElement, action) => {
            var promise;
            if (moduleElement.dataset.elementshortname == 'chapter') {
                var item = moduleElement.closest('.chapters-list');
                if (action == 'moveup') {
                    item.parentNode.insertBefore(item, item.previousElementSibling);
                } else {
                    item.parentNode.insertBefore(item, item.nextElementSibling.nextElementSibling);
                }
                let contents = [];
                var items = document.querySelectorAll('ul.course-content-list .chapters-content');
                items.forEach((item) => {
                    contents.push(item.dataset.id);
                });
                let params = {
                    chapters: contents.join(','),
                    cmid: cmID
                };
                promise = Fragment.loadFragment('mod_contentdesigner', 'move_chapter', contextID, params).done((html, js) => {
                    Templates.replaceNode('.contentdesigner-content', html, js);
                }).fail(Notification.exception);
                LoadingIcon.addIconToContainerRemoveOnCompletion(loaderItem, promise);
            } else {

                let chapter = moduleElement.closest('.chapters-content');
                let item = moduleElement.parentNode;
                if (action == 'moveup') {
                    // Append to the Previous chapter if this item is first in the list.
                    if (item.previousElementSibling === null) {
                        let previousChapter = item.closest('.chapters-list').previousElementSibling;
                        // To fix the moodle CI nested loop count of 5. Tested separetly.
                        var append = false;
                        if (previousChapter !== null) {
                            previousChapter.querySelector('.chapter-elements-list').append(item);
                            append = true;
                        }
                        if (append && previousChapter.childNodes[1] != undefined) {
                            updateChapterElements(previousChapter.childNodes[1]);
                        }
                    } else {
                        item.parentNode.insertBefore(item, item.previousElementSibling);
                    }
                } else {
                    // Prepend to the next chapter if this item is last in the list.
                    if (item.nextElementSibling === null) {
                        let nextChapter = item.closest('.chapters-list').nextElementSibling;
                        // To fix the moodle CI nested loop count of 5. Tested separetly.
                        var prepend = false;
                        if (nextChapter !== null) {
                            nextChapter.querySelector('.chapter-elements-list').prepend(item);
                            prepend = true;
                        }
                        if (prepend && nextChapter.childNodes[1] != undefined) {
                            updateChapterElements(nextChapter.childNodes[1]);
                        }

                    } else {
                        item.parentNode.insertBefore(item, item.nextElementSibling.nextElementSibling);
                    }
                }

                promise = updateChapterElements(chapter);
                LoadingIcon.addIconToContainerRemoveOnCompletion(loaderItem, promise);
            }
        };

        const updateChapterElements = (chapter) => {
            let contents = [];
            var items = chapter.querySelectorAll('li.element-item > div.element-item');
            items.forEach((item) => {
                contents.push(item.dataset.contentid);
            });
            let params = {
                contents: contents.join(','),
                chapterid: chapter.dataset.id,
                cmid: cmID
            };
            var promise = Fragment.loadFragment('mod_contentdesigner', 'move_element', contextID, params);
            promise.done((html, js) => {
                Templates.replaceNode('.contentdesigner-content', html, js);
            });

            return promise;
        };

        var updateStatus = (moduleElement) => {
            let statusElement = moduleElement.querySelector('[data-action="status"] > i');
            var params = {
                element: moduleElement.dataset.elementshortname,
                instance: moduleElement.dataset.instanceid,
                status: moduleElement.dataset.visibility == true ? false : true,
                cmid: cmID
            };

            if (moduleElement.dataset.visibility == true) {
                statusElement.classList.remove('fa-eye');
                statusElement.classList.add('fa-eye-slash');
                moduleElement.dataset.visibility = false;
            } else {
                statusElement.classList.remove('fa-eye-slash');
                statusElement.classList.add('fa-eye');
                moduleElement.dataset.visibility = true;
            }

            var promise = Fragment.loadFragment('mod_contentdesigner', 'update_visibility', contextID, params).then(() => {
                return true;
            });
            LoadingIcon.addIconToContainerRemoveOnCompletion(loaderItem, promise);
        };


        /**
         * Performs an action on a element (moving, deleting, duplicating, hiding, etc.)
         *
         * @param {JQuery} moduleElement activity element we perform action on
         * @param {Number} elementId
         * @param {Number} instanceId
         * @param {String} action Action of the current clicked element.
         */
        var editElement = function(moduleElement, elementId, instanceId, action) {
            var args = {
                cmid: cmID,
                action: action,
                elementid: elementId,
                instanceid: instanceId,
            };
            Fragment.loadFragment('mod_contentdesigner', 'edit_element', contextID, args).then((html) => {
                moduleElement.parentNode.replaceWith(html);
                return;
            }).fail(Notification.exception);
        };

        /**
         * Displays the delete confirmation to delete a module
         *
         * @param {String} element
         * @param {Function} onconfirm function to execute on confirm
         */
        var confirmDeleteElement = function(element, onconfirm) {
            var elementTypename = 'cdelement_'.element;
            Str.get_string('pluginname', elementTypename).done(function() {
                var plugindata = {
                    element: element
                };
                Str.get_strings([
                    {key: 'confirm', component: 'core'},
                    {key: 'deletechecktype', component: 'mod_contentdesigner', param: plugindata},
                    {key: 'yes'},
                    {key: 'no'}
                ]).done(function (s) {
                    Notification.confirm(s[0], s[1], s[2], s[3], onconfirm);
                }
                );
            });
        };

        /**
         * Load the elements list modal to insert new element
         *
         * @param {String} position where the element need to insert.
         * @param {Boolean} chapter chapter id to insert element.
         * @returns {Object}
         */
        const buildAddElementModal = (position = "bottom", chapter = 0) => {
            var params = {cmid: cmID};
            var promise;
            if ((typeof Modal.registerModalType !== 'undefined')) {
                promise = Modal.create({
                    type: ModalFactory.TYPE,
                    title: Str.get_string('addelement', 'contentdesigner'),
                    body: Fragment.loadFragment('mod_contentdesigner', 'get_elements_list', contextID, params),
                    large: false,
                });
            } else {
                promise = ModalFactory.create({
                    type: ModalFactory.TYPE,
                    title: Str.get_string('addelement', 'contentdesigner'),
                    body: Fragment.loadFragment('mod_contentdesigner', 'get_elements_list', contextID, params),
                    large: false,
                });
            }

            promise.then(modal => {
                modal.getRoot().on(ModalEvents.bodyRendered, function() {
                    modal.getRoot().get(0).querySelectorAll('.element-item').forEach((e) => {
                        e.addEventListener('click', function(e) {
                            if (e.target.closest('.element-item')) {
                                var element = e.currentTarget.dataset.element;
                                var params = {
                                    cmid: cmID,
                                    element: element,
                                    chapter: chapter,
                                    position: position,
                                    sesskey: M.cfg.sesskey
                                };
                                const urlParams = new URLSearchParams(params);
                                window.location = M.cfg.wwwroot + '/mod/contentdesigner/element.php?' + urlParams.toString();
                            }
                        });
                    });
                });
                modal.show();
                return modal;
            });
        };

        return {
            init: function(contextid, cmid) {
                return editor(contextid, cmid);
            }
        };
    });
