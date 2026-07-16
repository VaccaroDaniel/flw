define([], function() {
    'use strict';

    function initCard(card, index) {
        if (card.getAttribute('data-flw-accordion-ready') === '1') {
            return;
        }

        var toggle = card.querySelector('.flw-lesson-toggle');
        var body = card.querySelector('.flw-lesson-body');
        if (!toggle || !body) {
            return;
        }

        var bodyId = body.getAttribute('id') || 'flw-lesson-body-' + Date.now() + '-' + index;
        var expanded = toggle.getAttribute('aria-expanded') === 'true';

        body.setAttribute('id', bodyId);
        toggle.setAttribute('aria-controls', bodyId);
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        body.hidden = !expanded;
        card.setAttribute('data-flw-accordion-ready', '1');

        toggle.addEventListener('click', function() {
            var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
            body.hidden = isExpanded;
        });
    }

    function init(root) {
        var container = root || document;
        Array.prototype.slice.call(container.querySelectorAll('.flw-lesson-card')).forEach(initCard);
    }

    return {
        init: init
    };
});
