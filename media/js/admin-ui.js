window.ContentBuilderNgAdmin = window.ContentBuilderNgAdmin || (function () {
    function getTabTargetId(el) {
        if (!el || typeof el.getAttribute !== 'function') {
            return null;
        }

        return (
            el.getAttribute('aria-controls') ||
            el.getAttribute('data-tab') ||
            (el.getAttribute('data-bs-target') && el.getAttribute('data-bs-target').startsWith('#') ? el.getAttribute('data-bs-target').slice(1) : null) ||
            (el.getAttribute('href') && el.getAttribute('href').startsWith('#') ? el.getAttribute('href').slice(1) : null) ||
            (el.getAttribute('data-target') && el.getAttribute('data-target').startsWith('#') ? el.getAttribute('data-target').slice(1) : null)
        );
    }

    function initBootstrapTooltips(root) {
        var scope = root || document;

        if (!window.bootstrap || typeof window.bootstrap.Tooltip !== 'function') {
            return;
        }

        scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (!window.bootstrap.Tooltip.getInstance(el)) {
                new window.bootstrap.Tooltip(el);
            }
        });
    }

    function applyTabTooltips(tabsetId, tips, attempt) {
        var tabset = document.getElementById(tabsetId);
        var tries = typeof attempt === 'number' ? attempt : 0;

        if (!tabset || !tips) {
            return;
        }

        var jTab = tabset.matches('joomla-tab') ? tabset : tabset.querySelector('joomla-tab');
        if (!jTab) {
            return;
        }

        var selector = 'button[aria-controls],button[data-tab],button[data-target],button[data-bs-target],a[aria-controls],a[data-tab],a[data-target],a[data-bs-target],a[href^="#"]';
        var roots = [jTab];
        if (jTab.shadowRoot) {
            roots.push(jTab.shadowRoot);
        }

        var applied = 0;

        roots.forEach(function (root) {
            root.querySelectorAll(selector).forEach(function (trigger) {
                var id = getTabTargetId(trigger);
                var tip = id ? tips[id] : null;

                if (!tip) {
                    return;
                }

                trigger.setAttribute('title', String(tip));
                trigger.setAttribute('data-bs-toggle', 'tooltip');
                trigger.setAttribute('data-bs-placement', 'top');
                trigger.setAttribute('data-bs-title', String(tip));
                applied++;
            });

            initBootstrapTooltips(root);
        });

        if (applied === 0 && tries < 12) {
            window.setTimeout(function () {
                applyTabTooltips(tabsetId, tips, tries + 1);
            }, 120);
        }
    }

    function setHiddenInputValue(name, value) {
        var el = document.querySelector('input[name="' + name + '"], input[name="jform[' + name + ']"]');
        if (el) {
            el.value = value;
        }
    }

    function persistJoomlaTabset(tabsetId, storageKey, onSave, options) {
        var tabset = document.getElementById(tabsetId);
        var config = options || {};
        var restoreFromStorage = config.restoreFromStorage !== false;
        if (!tabset) {
            return;
        }

        var jTab = tabset.matches('joomla-tab') ? tabset : tabset.querySelector('joomla-tab');
        if (!jTab) {
            return;
        }

        var saved = restoreFromStorage ? localStorage.getItem(storageKey) : null;
        if (saved) {
            if (typeof jTab.show === 'function') {
                try {
                    jTab.show(saved);
                } catch (e) {
                }
            }

            var btn =
                jTab.querySelector('button[aria-controls="' + saved + '"]') ||
                jTab.querySelector('button[data-tab="' + saved + '"]') ||
                jTab.querySelector('button[data-bs-target="#' + saved + '"]') ||
                jTab.querySelector('button[data-target="#' + saved + '"]') ||
                jTab.querySelector('a[aria-controls="' + saved + '"]') ||
                jTab.querySelector('a[href="#' + saved + '"]') ||
                (jTab.shadowRoot && (
                    jTab.shadowRoot.querySelector('button[aria-controls="' + saved + '"]') ||
                    jTab.shadowRoot.querySelector('button[data-tab="' + saved + '"]') ||
                    jTab.shadowRoot.querySelector('button[data-bs-target="#' + saved + '"]') ||
                    jTab.shadowRoot.querySelector('button[data-target="#' + saved + '"]') ||
                    jTab.shadowRoot.querySelector('a[aria-controls="' + saved + '"]') ||
                    jTab.shadowRoot.querySelector('a[href="#' + saved + '"]')
                ));

            if (btn) {
                btn.click();
                if (typeof btn.blur === 'function') {
                    btn.blur();
                }
            }
        }

        var saveActiveTab = function (ev) {
            var trigger = (ev.target && typeof ev.target.closest === 'function') ? (ev.target.closest('button,a') || ev.target) : ev.target;
            var id = getTabTargetId(trigger);

            if (!id) {
                return;
            }

            localStorage.setItem(storageKey, id);
            if (typeof onSave === 'function') {
                onSave(id);
            }
        };

        jTab.addEventListener('click', saveActiveTab, { passive: true });

        if (jTab.shadowRoot) {
            jTab.shadowRoot.addEventListener('click', saveActiveTab, { passive: true });
        }
    }

    return {
        applyTabTooltips: applyTabTooltips,
        getTabTargetId: getTabTargetId,
        initBootstrapTooltips: initBootstrapTooltips,
        persistJoomlaTabset: persistJoomlaTabset,
        setHiddenInputValue: setHiddenInputValue
    };
}());
