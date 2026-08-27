/**
 * Drives the cookie banner. Reads and writes consent through window.TakeitCookie, which
 * the plugin's head output defines.
 */
(function () {
    'use strict';

    var api = window.TakeitCookie;

    if (!api) {
        return;
    }

    var root = document.querySelector('.cookie');

    if (!root) {
        return;
    }

    var badge = document.querySelector('.cookie_badge');
    var container = root.querySelector('.cookie_container');
    var moreButton = root.querySelector('.cookie_more');
    var lessButton = root.querySelector('.cookie_less');
    var denyButton = root.querySelector('.cookie_deny');
    var saveButton = root.querySelector('.cookie_save');
    var acceptButton = root.querySelector('.cookie_accept');

    function setActive(element, on) {
        if (on) {
            element.classList.add('active');
        } else {
            element.classList.remove('active');
        }
    }

    function boxes() {
        return root.querySelectorAll('.cookie_checkbox');
    }

    function boxFor(handle) {
        return root.querySelector('.cookie_checkbox[data-cookie="' + handle + '"]');
    }

    function applyValues(values) {
        var all = boxes();

        for (var i = 0; i < all.length; i++) {
            setActive(all[i], values[all[i].getAttribute('data-cookie')] === 1);
        }
    }

    function collectValues() {
        var values = {};
        var all = boxes();

        for (var i = 0; i < all.length; i++) {
            values[all[i].getAttribute('data-cookie')] = all[i].classList.contains('active') ? 1 : 0;
        }

        return values;
    }

    function everything(on) {
        var values = {};

        for (var i = 0; i < api.config.categories.length; i++) {
            values[api.config.categories[i]] = on ? 1 : 0;
        }

        return values;
    }

    function toggle(box) {
        if (box.classList.contains('disabled')) {
            return;
        }

        var on = !box.classList.contains('active');
        setActive(box, on);

        // Toggling a category carries its sub-categories with it.
        var children = api.config.children[box.getAttribute('data-cookie')];

        if (!children) {
            return;
        }

        for (var i = 0; i < children.length; i++) {
            var child = boxFor(children[i]);

            if (child && !child.classList.contains('disabled')) {
                setActive(child, on);
            }
        }
    }

    function showDetail(on) {
        if (container) {
            container.style.display = on ? 'block' : 'none';
        }

        if (moreButton) {
            moreButton.style.display = on ? 'none' : 'flex';
        }

        if (lessButton) {
            lessButton.style.display = on ? 'flex' : 'none';
        }

        if (saveButton) {
            saveButton.style.display = on ? 'flex' : 'none';
        }

        if (acceptButton) {
            acceptButton.style.display = on ? 'none' : 'flex';
        }

        // The decline button stays visible in both views.
    }

    function open(immediately) {
        root.style.transitionDelay = immediately ? '0s' : (api.config.revealDelay + 's');
        root.classList.add('visible');

        if (badge) {
            badge.classList.remove('visible');
        }
    }

    function close() {
        root.classList.remove('visible');

        if (badge && api.config.badge) {
            badge.classList.add('visible');
        }
    }

    function commit(values) {
        var result = api.save(values);

        close();

        // Withdrawing consent has to take out scripts that are already running.
        if (result.revoked) {
            window.setTimeout(function () {
                window.location.reload();
            }, 350);
        }
    }

    root.addEventListener('click', function (event) {
        var target = event.target;

        while (target && target !== root) {
            if (target.classList && target.classList.contains('cookie_checkbox')) {
                toggle(target);
                return;
            }

            target = target.parentNode;
        }
    });

    if (moreButton) {
        moreButton.addEventListener('click', function () {
            showDetail(true);
        });
    }

    if (lessButton) {
        lessButton.addEventListener('click', function () {
            showDetail(false);
        });
    }

    if (acceptButton) {
        acceptButton.addEventListener('click', function () {
            commit(everything(true));
        });
    }

    if (denyButton) {
        denyButton.addEventListener('click', function () {
            commit(everything(false));
        });
    }

    if (saveButton) {
        saveButton.addEventListener('click', function () {
            commit(collectValues());
        });
    }

    if (badge) {
        badge.addEventListener('click', function () {
            showDetail(false);
            applyValues(api.get());
            open(true);
        });
    }

    applyValues(api.get());
    showDetail(false);

    if (api.isDecided()) {
        if (badge && api.config.badge) {
            badge.classList.add('visible');
        }
    } else {
        open(false);
    }
})();
