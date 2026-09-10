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

    function nextIndex(current, links) {
        if (links.length < 2) {
            return current;
        }

        var total = links.reduce(function(sum, link, index) {
            return index === current ? sum : sum + Math.max(1, parseInt(link.getAttribute('data-rotating-ads-weight'), 10) || 1);
        }, 0);
        var pick = Math.floor(Math.random() * total) + 1;

        for (var index = 0; index < links.length; index += 1) {
            if (index === current) {
                continue;
            }
            pick -= Math.max(1, parseInt(links[index].getAttribute('data-rotating-ads-weight'), 10) || 1);
            if (pick <= 0) {
                return index;
            }
        }

        return current === 0 ? 1 : 0;
    }

    function loadImage(link) {
        var image = link.querySelector('.rotating-ad__image');
        var source = image && image.getAttribute('data-src');

        if (source) {
            image.setAttribute('src', source + '&view=' + Date.now() + Math.floor(Math.random() * 100000));
            image.removeAttribute('data-src');
        }
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
            var next = nextIndex(current, links);
            links[current].hidden = true;
            loadImage(links[next]);
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
