/* Menús desplegables de navegación superior — event delegation. */
const mainNav = document.querySelector('#mainNav');
const navToggle = document.querySelector('#navToggle');

function closeAllDropdowns() {
    document.querySelectorAll('.nav-dropdown.open').forEach((d) => {
        d.classList.remove('open');
        d.querySelector('.nav-dropdown-trigger')?.setAttribute('aria-expanded', 'false');
    });
}

function toggleDropdown(dropdown, trigger) {
    const wasOpen = dropdown.classList.contains('open');
    closeAllDropdowns();
    if (!wasOpen) {
        dropdown.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
    }
}

/* Delegación de clicks en el nav: maneja triggers y enlaces. */
mainNav?.addEventListener('click', (event) => {
    const trigger = event.target.closest('.nav-dropdown-trigger');
    if (trigger) {
        event.preventDefault();
        const dropdown = trigger.closest('.nav-dropdown');
        if (dropdown) toggleDropdown(dropdown, trigger);
        return;
    }
    const link = event.target.closest('a');
    if (link) {
        closeAllDropdowns();
        if (window.innerWidth <= 760) {
            mainNav.classList.remove('open');
            navToggle?.setAttribute('aria-expanded', 'false');
        }
    }
});

/* Teclado: Enter/Space en trigger, Escape global. */
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeAllDropdowns();
        if (mainNav?.classList.contains('open')) {
            mainNav.classList.remove('open');
            navToggle?.setAttribute('aria-expanded', 'false');
            navToggle?.focus();
        }
    }
});

mainNav?.addEventListener('keydown', (event) => {
    const trigger = event.target.closest('.nav-dropdown-trigger');
    if (trigger && (event.key === 'Enter' || event.key === ' ')) {
        event.preventDefault();
        const dropdown = trigger.closest('.nav-dropdown');
        if (dropdown) toggleDropdown(dropdown, trigger);
    }
});

/* Click fuera cierra dropdowns. */
document.addEventListener('click', (event) => {
    if (!event.target.closest('.nav-dropdown')) closeAllDropdowns();
    if (!event.target.closest('#navbar') && mainNav?.classList.contains('open')) {
        mainNav.classList.remove('open');
        navToggle?.setAttribute('aria-expanded', 'false');
    }
});

/* Menú móvil: toggle. */
navToggle?.addEventListener('click', () => {
    const isOpen = mainNav?.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', String(isOpen));
    if (isOpen) mainNav?.querySelector('a, button')?.focus();
});

const description = document.querySelector('#descripcion');
const counter = document.querySelector('#charCount');
const updateCounter = () => { if (description && counter) counter.textContent = `${description.value.length} caracteres`; };
description?.addEventListener('input', updateCounter);
updateCounter();

document.querySelectorAll('[data-password-toggle]').forEach((button) => button.addEventListener('click', (event) => {
    const password = document.querySelector(`#${button.dataset.passwordToggle || 'password'}`);
    if (!password) return;
    const visible = password.type === 'text';
    password.type = visible ? 'password' : 'text';
    event.currentTarget.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
    event.currentTarget.setAttribute('aria-pressed', String(!visible));
}));

const fileInput = document.querySelector('#adjuntos');
const fileList = document.querySelector('#fileList');
fileInput?.addEventListener('change', () => {
    if (!fileList) return;
    fileList.innerHTML = '';
    [...fileInput.files].forEach((file) => {
        const item = document.createElement('span');
        item.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(1)} MB`;
        fileList.appendChild(item);
    });
});

const pqrCreateForm = document.querySelector('[data-pqr-create-form]');
const pqrSubmitDialog = document.querySelector('#pqrSubmitDialog');
const pqrSubmitCancel = document.querySelector('#pqrSubmitCancel');
const pqrSubmitAccept = document.querySelector('#pqrSubmitAccept');
let pqrSubmitTrigger = null;
if (pqrCreateForm?.hasAttribute('data-has-errors')) {
    const firstInvalidField = pqrCreateForm.querySelector('[aria-invalid="true"]');
    if (firstInvalidField) {
        requestAnimationFrame(() => {
            firstInvalidField.focus({ preventScroll: true });
            firstInvalidField.scrollIntoView({
                block: 'center',
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            });
        });
    }
}

pqrCreateForm?.addEventListener('submit', (event) => {
    if (!pqrCreateForm.checkValidity() || pqrCreateForm.dataset.submitting === 'true') return;

    if (pqrCreateForm.dataset.confirmed !== 'true' && pqrSubmitDialog) {
        event.preventDefault();
        pqrSubmitTrigger = pqrCreateForm.querySelector('[data-radicacion-submit]');
        pqrSubmitDialog.showModal();
        requestAnimationFrame(() => pqrSubmitAccept?.focus());
        return;
    }

    pqrCreateForm.dataset.submitting = 'true';
    const submitButton = pqrCreateForm.querySelector('[data-radicacion-submit]');
    const submitLabel = pqrCreateForm.querySelector('[data-submit-label]');
    if (submitButton instanceof HTMLButtonElement) {
        submitButton.disabled = true;
        submitButton.setAttribute('aria-disabled', 'true');
        submitButton.setAttribute('aria-busy', 'true');
    }
    if (submitLabel) submitLabel.textContent = 'Radicando…';
});

const closePqrSubmitDialog = () => {
    if (pqrSubmitDialog?.open) pqrSubmitDialog.close();
};

pqrSubmitCancel?.addEventListener('click', closePqrSubmitDialog);
pqrSubmitDialog?.addEventListener('cancel', () => pqrSubmitTrigger?.focus());
pqrSubmitDialog?.addEventListener('close', () => {
    if (pqrCreateForm?.dataset.confirmed !== 'true') pqrSubmitTrigger?.focus();
});
pqrSubmitAccept?.addEventListener('click', () => {
    if (!pqrCreateForm || pqrCreateForm.dataset.submitting === 'true') return;
    pqrCreateForm.dataset.confirmed = 'true';
    pqrSubmitAccept.disabled = true;
    closePqrSubmitDialog();
    pqrCreateForm.requestSubmit();
});

const applyTheme = (theme) => {
    document.documentElement.dataset.theme = theme;
    document.querySelector('#themeToggle')?.setAttribute('aria-label', theme === 'dark' ? 'Activar modo claro' : 'Activar modo oscuro');
};
applyTheme(localStorage.getItem('theme') || 'light');
document.querySelector('#themeToggle')?.addEventListener('click', () => {
    const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('theme', theme);
    applyTheme(theme);
});

document.querySelector('#logo')?.addEventListener('change', (event) => {
    const file = event.currentTarget.files?.[0];
    const preview = document.querySelector('#brandPreview img');
    if (file && preview) preview.src = URL.createObjectURL(file);
});

document.querySelectorAll('.brand-logo-image[data-brand-fallback]').forEach((image) => {
    image.addEventListener('error', () => {
        const fallback = image.dataset.brandFallback;
        if (fallback && image.src !== fallback) image.src = fallback;
    }, { once: true });
});

const confirmDialog = document.querySelector('#confirmDialog');
let pendingConfirmation = null;
let confirmationTrigger = null;
const openConfirmation = (title, message, callback) => {
    if (!confirmDialog) return callback();
    document.querySelector('#confirmTitle').textContent = title;
    document.querySelector('#confirmMessage').textContent = message;
    pendingConfirmation = callback;
    confirmationTrigger = document.activeElement;
    confirmDialog.showModal();
    document.querySelector('#confirmCancel')?.focus();
};
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.dataset.confirm || form.dataset.confirmed === 'true') return;
    event.preventDefault();
    openConfirmation(form.dataset.confirm, form.dataset.confirmMessage || '¿Deseas continuar?', () => {
        form.dataset.confirmed = 'true';
        form.requestSubmit();
    });
});
document.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-confirm]');
    if (!button) return;
    event.preventDefault();
    openConfirmation(button.dataset.confirm, button.dataset.confirmMessage || '¿Deseas continuar?', () => button.form?.requestSubmit(button));
});
document.querySelector('#confirmCancel')?.addEventListener('click', () => { pendingConfirmation = null; confirmDialog.close(); confirmationTrigger?.focus(); });
document.querySelector('#confirmAccept')?.addEventListener('click', () => { const callback = pendingConfirmation; pendingConfirmation = null; confirmDialog.close(); callback?.(); });
confirmDialog?.addEventListener('cancel', () => { pendingConfirmation = null; requestAnimationFrame(() => confirmationTrigger?.focus()); });
document.querySelectorAll('.tag-color-input input[type="color"]').forEach((input) => input.addEventListener('input', () => { const code = input.parentElement.querySelector('code'); if (code) code.textContent = input.value.toUpperCase(); }));
document.querySelector('[data-first-error]')?.focus();
document.querySelectorAll('.tag-catalog form').forEach((form) => form.addEventListener('submit', (event) => {
    if (form.dataset.confirm && form.dataset.confirmed !== 'true') return;
    const button = event.submitter;
    if (!button) return;
    button.disabled = true;
    button.textContent = button.dataset.submitLabel || 'Guardando…';
}));
document.querySelectorAll('[data-tag-assignment]').forEach((form) => {
    const checkboxes = [...form.querySelectorAll('input[name="tags[]"]')];
    const summary = form.querySelector('[data-tag-summary]');
    const restore = form.querySelector('[data-tag-restore]');
    const submit = form.querySelector('[data-tag-submit]');
    const original = new Set(checkboxes.filter((checkbox) => checkbox.dataset.originallyChecked === 'true').map((checkbox) => checkbox.value));
    const update = () => {
        const selected = new Set(checkboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value));
        const added = [...selected].filter((id) => !original.has(id)).length;
        const removed = [...original].filter((id) => !selected.has(id)).length;
        const changed = added > 0 || removed > 0;
        summary.textContent = changed ? `${selected.size} ${selected.size === 1 ? 'etiqueta seleccionada' : 'etiquetas seleccionadas'}. Cambios pendientes: ${added} ${added === 1 ? 'añadida' : 'añadidas'} y ${removed} ${removed === 1 ? 'retirada' : 'retiradas'}.` : `${selected.size ? `${selected.size} ${selected.size === 1 ? 'etiqueta seleccionada' : 'etiquetas seleccionadas'}` : 'Ninguna etiqueta seleccionada'}. Sin cambios pendientes.`;
        restore.disabled = !changed;
        form.dataset.removesTags = String(removed > 0);
    };
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', update));
    restore.addEventListener('click', () => { checkboxes.forEach((checkbox) => { checkbox.checked = original.has(checkbox.value); }); update(); checkboxes[0]?.focus(); });
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        if (form.dataset.removesTags === 'true' && form.dataset.removalConfirmed !== 'true') {
            event.preventDefault();
            openConfirmation('Retirar etiquetas', 'Guardar retirará una o más etiquetas de esta PQRS. ¿Deseas continuar?', () => { form.dataset.removalConfirmed = 'true'; form.requestSubmit(); });
            return;
        }
        form.dataset.submitting = 'true'; submit.disabled = true; submit.textContent = 'Guardando…';
    });
    update();
});
const tagError = document.querySelector('[data-tag-error]');
if (tagError) requestAnimationFrame(() => tagError.focus());
document.querySelector('#responseTemplate')?.addEventListener('change', (event) => {
    const option = event.currentTarget.selectedOptions[0];
    if (option?.dataset.body) document.querySelector('#body').value = option.dataset.body;
});

/* C.3.5.2 — Estados de procesamiento en botones de comunicaciones */
document.querySelectorAll('form[data-reply-form], .internal-comments form, .reply-card.draft form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        if (form.dataset.confirm && form.dataset.confirmed !== 'true') return;
        form.dataset.submitting = 'true';
        const submitter = event.submitter;
        if (submitter instanceof HTMLButtonElement && submitter.dataset.submitLabel) {
            submitter.disabled = true;
            submitter.setAttribute('aria-disabled', 'true');
            submitter.setAttribute('aria-busy', 'true');
            submitter.textContent = submitter.dataset.submitLabel;
        }
    });
});

/* Previsualización de documentos */
const previewDialog = document.querySelector('#previewDialog');
const previewBody = document.querySelector('#previewBody');
const previewTitle = document.querySelector('#previewTitle');
const previewDownload = document.querySelector('#previewDownload');
const previewClose = document.querySelector('#previewClose');
const previewCloseBtn = document.querySelector('#previewCloseBtn');

const openPreview = (url, mime, title) => {
    if (!previewDialog) return;
    previewTitle.textContent = title || 'Vista previa';
    previewDownload.href = url;
    previewBody.innerHTML = '';

    if (mime && mime.startsWith('image/')) {
        const img = document.createElement('img');
        img.src = url;
        img.alt = title || 'Vista previa';
        previewBody.appendChild(img);
    } else if (mime === 'application/pdf') {
        const iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.title = title || 'Vista previa PDF';
        previewBody.appendChild(iframe);
    } else {
        previewBody.innerHTML = '<div class="preview-unsupported"><p>No se puede mostrar una vista previa de este archivo.</p></div>';
    }

    previewDialog.showModal();
};

const closePreview = () => {
    if (previewDialog?.open) {
        previewDialog.close();
        previewBody.innerHTML = '';
    }
};

previewClose?.addEventListener('click', closePreview);
previewCloseBtn?.addEventListener('click', closePreview);
previewDialog?.addEventListener('cancel', closePreview);

document.addEventListener('click', (event) => {
    const btn = event.target.closest('.document-preview-btn, .document-preview-thumb, .document-preview-cell');
    if (!btn) return;
    event.preventDefault();
    const url = btn.dataset.previewUrl;
    const mime = btn.dataset.previewMime;
    const title = btn.dataset.previewTitle;
    if (url) openPreview(url, mime, title);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    const cell = event.target.closest('.document-preview-cell');
    if (!cell) return;
    event.preventDefault();
    const url = cell.dataset.previewUrl;
    const mime = cell.dataset.previewMime;
    const title = cell.dataset.previewTitle;
    if (url) openPreview(url, mime, title);
});

/* Logo presentation controls */
const logoScale = document.querySelector('#logo_scale');
const logoOffsetX = document.querySelector('#logo_offset_x');
const logoOffsetY = document.querySelector('#logo_offset_y');
const logoScaleValue = document.querySelector('#logo_scale_value');
const logoOffsetXValue = document.querySelector('#logo_offset_x_value');
const logoOffsetYValue = document.querySelector('#logo_offset_y_value');
const logoReset = document.querySelector('#logoReset');
const logoPreviewImg = document.querySelector('#logoPreviewImg');
const previewLogin = document.querySelector('#previewLogin');
const previewNavbar = document.querySelector('#previewNavbar');

const updateLogoTransform = () => {
    if (!logoScale) return;
    const scale = logoScale.value;
    const offsetX = logoOffsetX?.value || 0;
    const offsetY = logoOffsetY?.value || 0;
    const transform = `scale(${scale}) translate(${offsetX}px, ${offsetY}px)`;

    if (logoPreviewImg) logoPreviewImg.style.transform = transform;
    if (previewLogin) previewLogin.style.transform = transform;
    if (previewNavbar) previewNavbar.style.transform = transform;

    if (logoScaleValue) logoScaleValue.textContent = parseFloat(scale).toFixed(2);
    if (logoOffsetXValue) logoOffsetXValue.textContent = offsetX;
    if (logoOffsetYValue) logoOffsetYValue.textContent = offsetY;
};

logoScale?.addEventListener('input', updateLogoTransform);
logoOffsetX?.addEventListener('input', updateLogoTransform);
logoOffsetY?.addEventListener('input', updateLogoTransform);

logoReset?.addEventListener('click', () => {
    if (logoScale) logoScale.value = 1;
    if (logoOffsetX) logoOffsetX.value = 0;
    if (logoOffsetY) logoOffsetY.value = 0;
    updateLogoTransform();
});

/* Logo file preview */
const logoInput = document.querySelector('#logo');
logoInput?.addEventListener('change', (event) => {
    const file = event.currentTarget.files?.[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    if (logoPreviewImg) logoPreviewImg.src = url;
    if (previewLogin) previewLogin.src = url;
    if (previewNavbar) previewNavbar.src = url;
});
