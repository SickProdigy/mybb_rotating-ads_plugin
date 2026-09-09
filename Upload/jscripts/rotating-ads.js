(function(document) {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }

        callback();
    }

    function randomDelay(minSeconds, maxSeconds) {
        var min = Math.max(1, parseInt(minSeconds, 10) || 1);
        var max = Math.max(min, parseInt(maxSeconds, 10) || min);
        var seconds = min === max ? min : min + Math.floor(Math.random() * (max - min + 1));

        return seconds * 1000;
    }

    function visibleIndex(links) {
        for (var index = 0; index < links.length; index += 1) {
            if (!links[index].hidden) {
                return index;
            }
        }

        return 0;
    }

    function nextIndex(current, total) {
        if (total < 2) {
            return current;
        }

        var next = current;
        while (next === current) {
            next = Math.floor(Math.random() * total);
        }

        return next;
    }

    function setupSlot(slot) {
        var links = Array.prototype.slice.call(slot.querySelectorAll('.rotating-ad__link'));

        if (links.length < 2) {
            return;
        }

        var min = slot.getAttribute('data-rotating-ads-min');
        var max = slot.getAttribute('data-rotating-ads-max');
        var current = visibleIndex(links);

        links.forEach(function(link, index) {
            link.hidden = index !== current;
        });

        function cycle() {
            var next = nextIndex(current, links.length);
            links[current].hidden = true;
            links[next].hidden = false;
            current = next;
            window.setTimeout(cycle, randomDelay(min, max));
        }

        window.setTimeout(cycle, randomDelay(min, max));
    }

    ready(function() {
        Array.prototype.forEach.call(
            document.querySelectorAll('.rotating-ad[data-rotating-ads="1"]'),
            setupSlot
        );
    });
}(document));
