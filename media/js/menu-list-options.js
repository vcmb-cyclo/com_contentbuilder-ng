(function () {
    'use strict';

    const config = window.Joomla && typeof window.Joomla.getOptions === 'function'
        ? window.Joomla.getOptions('com_contentbuilderng.menuListOptions', {})
        : {};
    const defaultsByForm = config.defaultsByForm || {};
    const useDefaultFormat = String(config.useDefaultFormat || 'Use Default (%s)');
    let resetting = false;

    function formatValue(format, value) {
        return format.includes('%s')
            ? format.replace('%s', String(value))
            : `${format} ${value}`;
    }

    function formatDefault(value) {
        return formatValue(useDefaultFormat, value);
    }

    function inheritedBoolean(value) {
        const normalized = String(value).trim().toLocaleLowerCase();

        if (value === 1 || ['1', 'yes', 'enabled'].includes(normalized)) {
            return 'yes';
        }

        if (value === 0 || ['0', 'no', 'disabled'].includes(normalized)) {
            return 'no';
        }

        return '';
    }

    function refreshColourState(field) {
        const value = String(field.value);
        const inherited = ['default', 'inherit', '-1'].includes(value)
            ? String(field.dataset.cbInheritedBoolean || '')
            : '';

        field.classList.toggle('form-select-success', value === 'yes' || value === '1');
        field.classList.toggle('form-select-danger', value === 'no' || value === '0' || value === 'disabled');
        field.classList.toggle('cb-form-select-inherited-success', inherited === 'yes');
        field.classList.toggle('cb-form-select-inherited-danger', inherited === 'no');
    }

    function updateInheritedValues(formId) {
        const defaults = defaultsByForm[String(formId)] || {};

        document.querySelectorAll('[data-cb-menu-default-key]').forEach((field) => {
            const key = String(field.dataset.cbMenuDefaultKey || '');
            let value = defaults[key] ?? (key === 'cb_list_limit' ? config.globalListLimit : '');

            if (field.tagName === 'SELECT') {
                const inheritValue = String(field.dataset.cbMenuInheritValue ?? '-1');
                const option = Array.from(field.options).find((item) => String(item.value) === inheritValue);

                if (option) {
                    field.dataset.cbInheritedBoolean = inheritedBoolean(value);
                    if (value === 1) {
                        value = config.yesLabel || 'Yes';
                    } else if (value === 0) {
                        value = config.noLabel || 'No';
                    }

                    option.textContent = formatDefault(value);
                    refreshColourState(field);
                }
            } else if (field.type === 'number') {
                field.placeholder = formatDefault(value);
            } else if (field.matches('[data-cb-list-limit-storage]')) {
                const inheritedValue = Number(value);
                const inheritedLabel = inheritedValue === 0 ? (config.allLabel || 'All') : value;
                field.closest('[data-cb-list-limit-control]')?.setAttribute('data-inherited', String(inheritedValue));
                field.closest('[data-cb-list-limit-control]')?.setAttribute('data-default-label', formatDefault(inheritedLabel));
                dispatchChange(field);
            }
        });

        document.querySelectorAll('[data-cb-view-default-key]').forEach((field) => {
            const key = String(field.dataset.cbViewDefaultKey || '');
            const option = Array.from(field.options || []).find((item) => ['default', 'inherit'].includes(String(item.value)));

            if (!option || defaults[key] === undefined) {
                return;
            }

            const value = Number(defaults[key]) === 1
                ? (config.yesLabel || 'Yes')
                : (config.noLabel || 'No');
            option.textContent = formatDefault(value);
            field.dataset.cbInheritedBoolean = inheritedBoolean(defaults[key]);
            refreshColourState(field);
        });
    }

    function dispatchChange(field) {
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function resetValue(field) {
        if (field.matches('[data-cb-new-list-config]')) {
            field.value = '{}';
        } else if (field.matches('[data-cb-list-limit-storage]')) {
            field.value = field.closest('[data-cb-list-limit-control]')?.dataset.inheritValue || '';
        } else if (field.tagName === 'SELECT' && field.dataset.cbMenuInheritValue !== undefined) {
            field.value = field.dataset.cbMenuInheritValue;
        } else {
            field.value = field.dataset.cbMenuResetValue || '';
        }

        dispatchChange(field);

        if (field.name) {
            field.dataset.cbMenuOriginalName = field.name;
            field.removeAttribute('name');
        }
    }

    function resetOverrides(button) {
        const confirmText = String(button.dataset.cbMenuResetConfirm || '');

        if (confirmText && !window.confirm(confirmText)) {
            return;
        }

        let names = [];

        try {
            names = JSON.parse(button.dataset.cbMenuResetNames || '[]');
        } catch (error) {
            names = [];
        }

        resetting = true;
        names.forEach((name) => {
            const selector = [
                `[name="jform[params][${window.CSS.escape(name)}]"]`,
                `[name="jform[params][settings][${window.CSS.escape(name)}]"]`,
            ].join(',');

            document.querySelectorAll(selector).forEach(resetValue);
        });

        document.querySelectorAll('[data-cb-menu-filter-fields] input').forEach((field) => {
            field.value = '';
            dispatchChange(field);
        });

        if (typeof window.cb_value === 'object') {
            window.cb_value = {};
        }

        if (typeof window.cb_value_order === 'object') {
            window.cb_value_order = {};
        }
        resetting = false;
    }

    function restoreSubmittedName(event) {
        const field = event.target;

        if (resetting || !field.dataset || !field.dataset.cbMenuOriginalName) {
            return;
        }

        field.name = field.dataset.cbMenuOriginalName;
        delete field.dataset.cbMenuOriginalName;
    }

    function initialiseListBuilder(root) {
        if (root.dataset.cbListBuilderReady === '1') {
            return;
        }

        const storage = document.getElementById(String(root.dataset.cbConfigInput || ''));

        if (!storage) {
            return;
        }

        root.querySelectorAll('[data-cb-native-field-slot]').forEach((slot) => {
            const fieldName = String(slot.dataset.cbNativeFieldSlot || '');
            const escapedName = window.CSS.escape(fieldName);
            const field = document.querySelector([
                `[name="jform[params][${escapedName}]"]`,
                `[name="jform[params][settings][${escapedName}]"]`,
            ].join(','));
            const group = field?.closest('.control-group, .form-group, .mb-3');

            if (group && !slot.contains(group)) {
                slot.append(group);
            }
        });

        root.dataset.cbListBuilderReady = '1';
        let state = {};
        let writingStorage = false;

        const readState = () => {
            try {
                const parsed = JSON.parse(storage.value || '{}');
                state = parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
            } catch (error) {
                state = {};
            }
        };

        const setPath = (path, value) => {
            const parts = path.split('.');
            let target = state;

            while (parts.length > 1) {
                const part = parts.shift();
                target[part] = target[part] && typeof target[part] === 'object' ? target[part] : {};
                target = target[part];
            }

            target[parts[0]] = value;
        };

        const getPath = (path) => {
            let value = state;
            path.split('.').forEach((part) => {
                value = value && typeof value === 'object' ? value[part] : undefined;
            });

            return value;
        };

        const setCapabilityState = (field, disabled, available) => {
            field.disabled = disabled;
            const cell = field.closest('.cb-menu-capability-cell');

            if (!cell) {
                return;
            }

            cell.classList.toggle('is-inherited', disabled && available);
            cell.classList.toggle('is-unavailable', disabled && !available);
            cell.title = disabled
                ? String(available
                    ? root.dataset.cbInheritedControlTitle || ''
                    : root.dataset.cbUnavailableControlTitle || '')
                : '';
        };

        const refresh = () => {
            root.querySelectorAll('[data-cb-show-when]').forEach((element) => {
                const [path, value] = element.dataset.cbShowWhen.split(':');
                element.hidden = String(getPath(path) ?? '') !== value;
            });

            const customColumns = state.columnsMode === 'custom';
            root.querySelectorAll('[data-cb-column]').forEach((field) => {
                const row = field.closest('tr');
                const locallyPublished = row?.querySelector('[data-cb-published-field]')?.checked !== false;
                const available = row?.dataset.canList === '1' && locallyPublished;
                setCapabilityState(field, !customColumns || !available, available);
            });
            root.querySelectorAll('[data-cb-search-field]').forEach((field) => {
                const row = field.closest('tr');
                const locallyPublished = row?.querySelector('[data-cb-published-field]')?.checked !== false;
                const available = row?.dataset.canSearch === '1' && locallyPublished;
                setCapabilityState(field, !customColumns || !available, available);
            });
            root.querySelectorAll('[data-cb-link-field]').forEach((field) => {
                const row = field.closest('tr');
                const locallyPublished = row?.querySelector('[data-cb-published-field]')?.checked !== false;
                const available = row?.dataset.canLink === '1' && locallyPublished;
                setCapabilityState(field, !customColumns || !available, available);
            });
            ['detail', 'edit', 'export'].forEach((capability) => {
                root.querySelectorAll(`[data-cb-${capability}-field]`).forEach((field) => {
                    const row = field.closest('tr');
                    const locallyPublished = row?.querySelector('[data-cb-published-field]')?.checked !== false;
                    const available = row?.dataset[`can${capability[0].toUpperCase()}${capability.slice(1)}`] === '1'
                        && locallyPublished;
                    setCapabilityState(field, !customColumns || !available, available);
                });
            });
            root.querySelectorAll('[data-cb-published-field]').forEach((field) => {
                const available = field.closest('tr')?.dataset.canPublished === '1';
                setCapabilityState(field, !customColumns || !available, available);
            });
            root.querySelectorAll('[data-cb-filter]').forEach((field) => {
                const row = field.closest('tr');
                field.disabled = row?.dataset.canPublished !== '1'
                    || (customColumns && row?.querySelector('[data-cb-published-field]')?.checked === false);
            });
            root.querySelectorAll('.form-select-color-state').forEach(refreshColourState);
        };

        const sync = () => {
            root.querySelectorAll('[data-cb-key]').forEach((field) => {
                setPath(field.dataset.cbKey, field.type === 'number' ? Number(field.value || 0) : field.value);
            });
            state.searchFields = Array.from(root.querySelectorAll('[data-cb-search-field]:checked')).map((field) => field.value);
            state.linkFields = Array.from(root.querySelectorAll('[data-cb-link-field]:checked')).map((field) => field.value);
            state.detailFields = Array.from(root.querySelectorAll('[data-cb-detail-field]:checked')).map((field) => field.value);
            state.editFields = Array.from(root.querySelectorAll('[data-cb-edit-field]:checked')).map((field) => field.value);
            state.exportFields = Array.from(root.querySelectorAll('[data-cb-export-field]:checked')).map((field) => field.value);
            state.publishedFields = Array.from(root.querySelectorAll('[data-cb-published-field]:checked')).map((field) => field.value);
            state.columns = Array.from(root.querySelectorAll('[data-cb-column]:checked')).map((field) => field.value);
            state.columnOrder = Array.from(root.querySelectorAll('[data-cb-column-row]'))
                .map((row) => String(row.dataset.reference || ''))
                .filter((reference) => reference !== '');
            state.filters = {};
            root.querySelectorAll('[data-cb-column-row]').forEach((row) => {
                const filter = row.querySelector('[data-cb-filter]');
                const value = filter ? filter.value.trim() : '';
                if (value) {
                    state.filters[row.dataset.reference] = value;
                }
            });
            state.sort = [];
            for (let index = 0; index < 3; index += 1) {
                const field = root.querySelector(`[data-cb-sort-field="${index}"]`);
                const direction = root.querySelector(`[data-cb-sort-dir="${index}"]`);
                if (field && direction && field.value) {
                    state.sort.push({ field: field.value, dir: direction.value });
                }
            }

            writingStorage = true;
            storage.value = JSON.stringify(state);
            storage.dispatchEvent(new Event('input', { bubbles: true }));
            storage.dispatchEvent(new Event('change', { bubbles: true }));
            writingStorage = false;
            refresh();
        };

        const applyState = () => {
            root.querySelectorAll('[data-cb-key]').forEach((field) => {
                const value = getPath(field.dataset.cbKey);
                if (value !== undefined) {
                    field.value = String(value);
                } else if (field.dataset.cbResetValue !== undefined) {
                    field.value = String(field.dataset.cbResetValue);
                }

                if (field.matches('[data-cb-list-limit-storage]')) {
                    field.dispatchEvent(new Event('input'));
                }
            });

            const configuredSort = Array.isArray(state.sort) ? state.sort : [];
            for (let index = 0; index < 3; index += 1) {
                const sort = configuredSort[index] && typeof configuredSort[index] === 'object'
                    ? configuredSort[index] : {};
                const field = root.querySelector(`[data-cb-sort-field="${index}"]`);
                const direction = root.querySelector(`[data-cb-sort-dir="${index}"]`);
                if (field) {
                    field.value = String(sort.field || '');
                }
                if (direction) {
                    direction.value = String(sort.field || '') === ''
                        ? 'asc' : (String(sort.dir || 'asc') === 'desc' ? 'desc' : 'asc');
                }
            }

            const hasSearchFields = Array.isArray(state.searchFields);
            const searchFields = new Set(hasSearchFields ? state.searchFields.map(String) : []);
            root.querySelectorAll('[data-cb-search-field]').forEach((field) => {
                field.checked = field.closest('tr')?.dataset.canSearch === '1' && (state.columnsMode === 'custom'
                    ? (hasSearchFields ? searchFields.has(String(field.value)) : field.dataset.viewDefault === '1')
                    : field.dataset.viewDefault === '1');
            });

            const hasLinkFields = Array.isArray(state.linkFields);
            const linkFields = new Set(hasLinkFields ? state.linkFields.map(String) : []);
            root.querySelectorAll('[data-cb-link-field]').forEach((field) => {
                field.checked = field.closest('tr')?.dataset.canLink === '1' && (state.columnsMode === 'custom'
                    ? (hasLinkFields ? linkFields.has(String(field.value)) : field.dataset.viewDefault === '1')
                    : field.dataset.viewDefault === '1');
            });

            ['detail', 'edit', 'export', 'published'].forEach((capability) => {
                const stateKey = `${capability}Fields`;
                const hasFields = Array.isArray(state[stateKey]);
                const fields = new Set(hasFields ? state[stateKey].map(String) : []);
                root.querySelectorAll(`[data-cb-${capability}-field]`).forEach((field) => {
                    const row = field.closest('tr');
                    const allowed = row?.dataset[`can${capability[0].toUpperCase()}${capability.slice(1)}`] === '1';
                    field.checked = allowed && (state.columnsMode === 'custom'
                        ? (hasFields ? fields.has(String(field.value)) : field.dataset.viewDefault === '1')
                        : field.dataset.viewDefault === '1');
                });
            });

            const hasColumns = Array.isArray(state.columns);
            const columns = new Set(hasColumns ? state.columns.map(String) : []);
            root.querySelectorAll('[data-cb-column]').forEach((field) => {
                field.checked = field.closest('tr')?.dataset.canList === '1' && (state.columnsMode === 'custom'
                    ? (hasColumns ? columns.has(String(field.value)) : field.dataset.viewDefault === '1')
                    : field.dataset.viewDefault === '1');
            });

            if (state.columnsMode === 'custom') {
                const rows = Array.from(root.querySelectorAll('[data-cb-column-row]'));
                const body = root.querySelector('[data-cb-column-rows]');
                const configuredOrder = Array.isArray(state.columnOrder)
                    ? state.columnOrder.map(String)
                    : (hasColumns ? state.columns.map(String) : []);
                const positions = new Map(configuredOrder.map((reference, index) => [reference, index]));
                rows.sort((left, right) => {
                    const leftPosition = positions.get(String(left.dataset.reference || ''));
                    const rightPosition = positions.get(String(right.dataset.reference || ''));
                    const leftOrder = leftPosition === undefined ? Number.MAX_SAFE_INTEGER : leftPosition;
                    const rightOrder = rightPosition === undefined ? Number.MAX_SAFE_INTEGER : rightPosition;

                    return leftOrder - rightOrder
                        || Number(left.dataset.viewOrder || 0) - Number(right.dataset.viewOrder || 0);
                });
                rows.forEach((row) => body?.appendChild(row));
            }

            if (state.columnsMode !== 'custom') {
                const rows = Array.from(root.querySelectorAll('[data-cb-column-row]'));
                const body = root.querySelector('[data-cb-column-rows]');
                rows.sort((left, right) => Number(left.dataset.viewOrder || 0) - Number(right.dataset.viewOrder || 0));
                rows.forEach((row) => body?.appendChild(row));
            }

            const filters = state.filters && typeof state.filters === 'object' ? state.filters : {};
            root.querySelectorAll('[data-cb-column-row]').forEach((row) => {
                const filter = row.querySelector('[data-cb-filter]');
                if (filter) {
                    filter.value = String(filters[row.dataset.reference] || '');
                }
            });
            refresh();
        };

        const titleField = root.querySelector('[data-cb-key="title"]');
        const titleCounter = root.querySelector('[data-cb-title-character-count]');
        const refreshTitleCounter = () => {
            if (!titleField || !titleCounter) {
                return;
            }

            titleField.value = titleField.value.replace(/\r\n?/g, '\n');
            const characters = Array.from(titleField.value);
            if (characters.length > 255) {
                titleField.value = characters.slice(0, 255).join('');
            }
            const lineCount = titleField.value.split('\n').length;
            const lineLabel = lineCount === 1
                ? String(titleCounter.dataset.cbLineLabelOne || 'line')
                : String(titleCounter.dataset.cbLineLabelMany || 'lines');
            titleCounter.textContent = `${Array.from(titleField.value).length}/255 · ${lineCount} ${lineLabel}`;

            if (titleField.matches('textarea')) {
                const styles = window.getComputedStyle(titleField);
                const lineHeight = Number.parseFloat(styles.lineHeight) || 24;
                const chrome = (Number.parseFloat(styles.paddingTop) || 0)
                    + (Number.parseFloat(styles.paddingBottom) || 0)
                    + (Number.parseFloat(styles.borderTopWidth) || 0)
                    + (Number.parseFloat(styles.borderBottomWidth) || 0);
                const minHeight = (lineHeight * 2) + chrome;
                const maxHeight = (lineHeight * 5) + chrome;
                titleField.style.height = 'auto';
                const height = Math.min(maxHeight, Math.max(minHeight, titleField.scrollHeight));
                titleField.style.height = `${height}px`;
                titleField.classList.toggle('is-scrollable', titleField.scrollHeight > maxHeight);
            }
        };

        readState();
        applyState();
        refreshTitleCounter();
        root.addEventListener('input', (event) => {
            if (event.target === storage || event.target.closest('[data-cb-native-field-slot]')) {
                return;
            }
            if (event.target === titleField) {
                refreshTitleCounter();
            }
            sync();
        });
        root.addEventListener('change', (event) => {
            if (event.target === storage || event.target.closest('[data-cb-native-field-slot]')) {
                return;
            }
            const sortField = event.target.closest('[data-cb-sort-field]');
            if (sortField && sortField.value === '') {
                const direction = root.querySelector(`[data-cb-sort-dir="${sortField.dataset.cbSortField}"]`);
                if (direction) {
                    direction.value = 'asc';
                }
            }
            sync();
        });
        root.addEventListener('click', (event) => {
            const button = event.target.closest('[data-cb-move]');
            if (!button || state.columnsMode !== 'custom') {
                return;
            }

            const row = button.closest('tr');
            const sibling = button.dataset.cbMove === 'up' ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling) {
                return;
            }
            if (button.dataset.cbMove === 'up') {
                row.parentNode.insertBefore(row, sibling);
            } else {
                row.parentNode.insertBefore(sibling, row);
            }
            sync();
        });

        const search = root.querySelector('[data-cb-field-search]');
        if (search) {
            search.addEventListener('input', () => {
                const query = search.value.trim().toLocaleLowerCase();
                root.querySelectorAll('[data-cb-column-row]').forEach((row) => {
                    row.hidden = query !== '' && !row.dataset.label.includes(query);
                });
            });
        }

        storage.addEventListener('change', () => {
            if (!writingStorage) {
                readState();
                applyState();
            }
        });
        storage.closest('form')?.addEventListener('submit', () => {
            if (!storage.dataset.cbMenuOriginalName) {
                sync();
            }
        }, true);
    }

    function initListBuilders() {
        document.querySelectorAll('[data-cb-new-list-builder]').forEach(initialiseListBuilder);
    }

    function init() {
        const viewSelector = document.querySelector('[data-cb-menu-view-selector]');

        if (viewSelector) {
            updateInheritedValues(viewSelector.value);
            viewSelector.addEventListener('change', () => {
                updateInheritedValues(viewSelector.value);

                if (typeof window.contentbuilderng_setFormId === 'function') {
                    window.contentbuilderng_setFormId(viewSelector.value);
                }
            });
        }

        document.querySelectorAll('[data-cb-menu-reset-overrides]').forEach((button) => {
            button.addEventListener('click', () => resetOverrides(button));
        });
        document.addEventListener('input', restoreSubmittedName, true);
        document.addEventListener('change', restoreSubmittedName, true);
        document.querySelectorAll('.form-select-color-state').forEach(refreshColourState);
        document.addEventListener('change', (event) => {
            if (event.target.matches?.('.form-select-color-state')) {
                refreshColourState(event.target);
            }
        });
        initListBuilders();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
