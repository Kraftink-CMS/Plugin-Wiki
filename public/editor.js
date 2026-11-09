const initialized = new WeakSet();

function initializeEditors(root = document) {
    for (const form of root.querySelectorAll('[data-document-editor]')) {
        if (initialized.has(form)) {
            continue;
        }
        initialized.add(form);
        const locale = form.querySelector('[data-document-locale]');
        const translation = form.querySelector('[data-document-translation]');
        const updateTranslations = () => {
            for (const option of translation.options) {
                const unavailable = option.value !== '' && option.dataset.locale === locale.value;
                option.disabled = unavailable;
                option.hidden = unavailable;
                if (unavailable && option.selected) {
                    translation.value = '';
                }
            }
        };
        locale.addEventListener('change', updateTranslations);
        updateTranslations();

        form.noValidate = true;
        const showField = (field) => {
            const pane = field?.closest('.tab-pane');
            if (pane) {
                form.querySelector(`[data-bs-target="#${pane.id}"]`)?.click();
            }
        };
        form.addEventListener('submit', (event) => {
            window.tinymce?.triggerSave();
            if (form.checkValidity()) {
                return;
            }
            event.preventDefault();
            const field = form.querySelector(':invalid');
            showField(field);
            window.setTimeout(() => {
                field?.focus();
                field?.reportValidity();
            }, 180);
        });
        for (const key of JSON.parse(form.dataset.documentErrors || '[]')) {
            const field = [...form.elements].find((element) => element.name === key);
            if (field) {
                showField(field);
                break;
            }
        }
    }
}

initializeEditors();
document.addEventListener('kraft:content-added', (event) => initializeEditors(event.target));
