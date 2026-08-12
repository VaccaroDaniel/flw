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
 * Initializes and manages H5P elements within a Moodle environment.
 *
 * This module defines functions to handle user interactions with H5P content,
 * capturing xAPI events, and storing user responses. It listens for specific
 * xAPI verbs such as 'answered', 'completed', and 'interacted' to determine
 * when to store user responses. The module also handles the removal of warning
 * messages and refreshes content elements upon successful data storage.
 *
 * @module cdelement_h5p/h5p
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'mod_contentdesigner/elements', 'core/ajax', 'core/notification'], function($, Elements, AJAX, Notification) {

    var interactedInstances = [];

    /**
     * H5P element. Get the user reponse after attempt and send a request to store data in moodle.
     *
     * @param {int} instance
     */
    const elementH5P = (instance) => {
        let instanceElem = document.querySelector('.h5p-element-instance[data-instanceid="' + instance + '"]');
        var iframe = instanceElem.querySelector('.h5p-iframe');
        iframe.onload = () => h5pExternal(instance);
    };

    const h5pExternal = (instance) => {

        let instanceElem = document.querySelector('.h5p-element-instance[data-instanceid="' + instance + '"]');
        var iframe = instanceElem.querySelector('.h5p-iframe');

        if (iframe.contentWindow.H5P == undefined) {
            setTimeout(() => elementH5P(instance), 200);
            return;
        }

        var h5p = iframe.contentWindow.H5P;

        if (h5p.externalDispatcher === undefined) {
            setTimeout(() => elementH5P(instance), 200);
            return;
        }

        h5p.externalDispatcher.on('xAPI', function(event) {

            // Skip malformed events.
            var hasStatement = event && event.data && event.data.statement;
            if (!hasStatement) {
                return;
            }

            var statement = event.data.statement;
            var validVerb = statement.verb && statement.verb.id;
            if (!validVerb) {
                return;
            }

            var isCompleted = statement.verb.id === 'http://adlnet.gov/expapi/verbs/answered'
                || statement.verb.id === 'http://adlnet.gov/expapi/verbs/completed';

            var isChild = statement.context && statement.context.contextActivities &&
                statement.context.contextActivities.parent &&
                statement.context.contextActivities.parent[0] &&
                statement.context.contextActivities.parent[0].id;
            // Attempted response only stored.
            var isInteract = statement.verb.id === 'http://adlnet.gov/expapi/verbs/interacted';
            var isInteracted = false;
            var isResponsed = false;
            var extensionID;
            if (isInteract) {
                try {
                    extensionID = statement.object.definition.extensions['http://h5p.org/x-api/h5p-local-content-id'];
                    interactedInstances[extensionID] = true;
                } catch (err) {
                    Notification.alert(err);
                }
                return;
            } else {
                try {
                    extensionID = statement.object.definition.extensions['http://h5p.org/x-api/h5p-local-content-id'];
                    isInteracted = interactedInstances[extensionID] ?? false;
                } catch (err) {
                    Notification.alert(err);
                }
            }

            if (statement.result === undefined) {
                return;
            }
            // Remove the separator[,] from response.
            if (statement.result.response !== undefined) {
                var max = statement.result.score.max ?? 0;
                var response = statement.result.response;
                for (var i = 1; i <= max; i++) {
                    response = response.replace('[,]', '');
                }
                isResponsed = (response != '');
            } else {
                // Response is not available.
                isResponsed = true;
            }

            // If h5p has grade setup then student should pass all.
            var isPassed = (statement.result.score.max < 1
                || (statement.result.score.max == statement.result.score.raw)
                || (statement.result.success !== undefined && statement.result.success == true));

            if (isCompleted && !isChild && isResponsed && isInteracted && isPassed) {
                var promises = storeUserResponse(statement, instance);
                if (!promises) {
                    return;
                }
                promises[0].then((response) => {
                    if (response) {
                        // Remove the warning message.
                        removeWarning();
                        // Update the other elemnets and chapters.
                        Elements.refreshContent();
                    }
                    return;
                }).catch(Notification.exception);
            }
        });
    };

    /**
     * Remove the warning from response.
     */
    const removeWarning = () => {
        if (Elements.courseContent().querySelector('.label.label-warning') !== null) {
            Elements.courseContent().querySelector('.label.label-warning').remove();
        }
    };

    /**
     * Send the request to store the user h5p response.
     * @param {Object} statement
     * @param {int} instance
     * @returns {Object}
     */
    const storeUserResponse = (statement, instance) => {

        var params = {
            cmid: Elements.contentDesignerData().cmid,
            instanceid: instance,
            result: {
                completion: statement.result.completion ?? 0,
                success: statement.result.success ?? 0,
                duration: statement.result.duration ?? '',
                response: statement.result.response ?? '',
                score: {
                    min: statement.result.score.min ?? 0,
                    max: statement.result.score.max ?? 0,
                    raw: statement.result.score.raw ?? 0,
                    scaled: statement.result.score.scaled ?? 0
                }
            },
        };

        var promises = AJAX.call([{
            methodname: 'cdelement_h5p_store_result',
            args: params
        }]);

        return promises;
    };

    return {
        init: function(instance) {
            elementH5P(instance);
        }
    };
});
