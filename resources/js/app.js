const profileMenu = document.querySelector('[data-menu]');
const profileToggle = document.querySelector('[data-menu-toggle]');

profileToggle?.addEventListener('click', () => {
    const isOpen = profileMenu?.classList.toggle('is-open') ?? false;
    profileToggle.setAttribute('aria-expanded', String(isOpen));
});

document.addEventListener('click', (event) => {
    if (profileMenu && ! profileMenu.contains(event.target)) {
        profileMenu.classList.remove('is-open');
        profileToggle?.setAttribute('aria-expanded', 'false');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        profileMenu?.classList.remove('is-open');
        profileToggle?.setAttribute('aria-expanded', 'false');
    }
});

// Repeatable staff/programme rows retain unique field indices after removals.
document.querySelectorAll('[data-source-table]').forEach((table) => {
    const body = table.querySelector('[data-source-rows]');
    const prototype = body?.querySelector('tr')?.cloneNode(true);
    let index = Math.max(-1, ...Array.from(body?.querySelectorAll('input,select,textarea') ?? []).map((field) => Number(field.name.match(/\[(\d+)\]/)?.[1] ?? -1))) + 1;
    table.querySelector('[data-add-source-row]')?.addEventListener('click', () => {
        if (!prototype || !body) return;
        index = Math.max(index, ...Array.from(body.querySelectorAll('[name]')).map(field => Number(field.name.match(/\[(\d+)\]/)?.[1] ?? -1) + 1));
        const row = prototype.cloneNode(true);
        row.querySelectorAll('.source-inline-error').forEach(error => error.remove());
        row.querySelectorAll('input,select,textarea').forEach((field) => {
            field.name = field.name.replace(/\[\d+\]/, `[${index}]`);
            field.value = '';
            field.removeAttribute('aria-invalid');
            field.removeAttribute('aria-describedby');
        });
        row.querySelector('th').textContent = index + 1;
        row.querySelectorAll('.source-total').forEach((cell) => cell.textContent = '0');
        index++;
        body.append(row);
        table.closest('form')?.dispatchEvent(new Event('formstructurechange'));
        row.querySelector('input,select,textarea')?.focus();
    });
    table.addEventListener('click', (event) => {
        if (event.target.closest('[data-remove-source-row]')) {
            const row = event.target.closest('tr');
            if (body.children.length > 1) row.remove();
            else row.querySelectorAll('input,select,textarea').forEach((field) => field.value = '');
            table.closest('form')?.dispatchEvent(new Event('formstructurechange'));
        }
    });
});

// Open the section selected in a school profile's contents menu.
document.querySelectorAll('.school-profile-nav a[href^="#"]').forEach((link) => {
    link.addEventListener('click', () => {
        const section = document.getElementById(link.getAttribute('href').slice(1));
        if (section?.tagName === 'DETAILS') section.open = true;
    });
});



// Warn only when editable form values differ from their original values.
const protectedForms = new Map();
const formFingerprint = (form) => JSON.stringify(Array.from(new FormData(form).entries()).filter(([key]) => key !== 'draft_revision').map(([key,value]) => [key,typeof value === 'string' ? value : value.name]));
document.querySelectorAll('form[data-protect-changes]').forEach((form) => {
    const state = { initial:formFingerprint(form), dirty:false, submitting:false };
    protectedForms.set(form,state);
    let indicator = form.querySelector('[data-save-state]');
    const refresh = () => {
        state.dirty = formFingerprint(form) !== state.initial;
        if (indicator) {
            indicator.textContent = state.dirty ? 'Unsaved changes' : 'No unsaved changes';
            indicator.classList.toggle('is-dirty',state.dirty);
        }
    };
    form.addEventListener('input',refresh);
    form.addEventListener('change',refresh);
    form.addEventListener('formstructurechange',refresh);
    form.addEventListener('submit',() => { state.submitting=true; });
    refresh();
});
const hasUnsavedChanges = () => Array.from(protectedForms.values()).some((state) => state.dirty && !state.submitting);
window.addEventListener('beforeunload',(event) => {
    if(hasUnsavedChanges()) { event.preventDefault(); event.returnValue=''; }
});
document.addEventListener('click',(event) => {
    const link = event.target.closest('a[href]');
    if(!link || link.target === '_blank' || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.hasAttribute('download')) return;
    const target = new URL(link.href,window.location.href);
    if(target.pathname === window.location.pathname && target.search === window.location.search && target.hash) return;
    if ([...protectedForms.keys()].some(form => form.dataset.autosaveUrl)) return;
    if(hasUnsavedChanges()) {
        if(!window.confirm('You have unsaved changes. Leave this page without saving?')) event.preventDefault();
        else protectedForms.forEach((state) => { state.submitting=true; });
    }
});
// Profile links support keyboard navigation as well as normal Tab navigation.
profileToggle?.addEventListener('keydown',(event) => {
    if(event.key === 'ArrowDown') {
        event.preventDefault();
        profileMenu.classList.add('is-open');
        profileToggle.setAttribute('aria-expanded','true');
        profileMenu.querySelector('.profile-dropdown a,.profile-dropdown button')?.focus();
    }
});
profileMenu?.addEventListener('keydown',(event) => {
    const items=Array.from(profileMenu.querySelectorAll('.profile-dropdown a,.profile-dropdown button'));
    const index=items.indexOf(document.activeElement);
    if(event.key==='Escape') { profileMenu.classList.remove('is-open'); profileToggle.setAttribute('aria-expanded','false'); profileToggle.focus(); }
    if(index>=0 && ['ArrowDown','ArrowUp','Home','End'].includes(event.key)) {
        event.preventDefault();
        const next=event.key==='Home'?0:event.key==='End'?items.length-1:(index+(event.key==='ArrowDown'?1:-1)+items.length)%items.length;
        items[next]?.focus();
    }
});

window.addEventListener('pageshow',() => protectedForms.forEach((state) => { state.submitting=false; }));

// Acknowledged annual submission, separate from saving draft sections.
document.querySelectorAll('[data-confirm-submission]').forEach(form => {
    const dialog = document.getElementById('submission-confirmation');
    if (!dialog) return;
    form.addEventListener('submit', event => {
        if (hasUnsavedChanges()) {
            event.preventDefault();
            window.alert('Save your changes before submitting the form.');
            return;
        }
        if (form.elements.acknowledged.value === '1') return;
        event.preventDefault();
        dialog.returnValue = '';
        dialog.querySelector('input[type=checkbox]').checked = false;
        dialog.showModal();
    });
    dialog.addEventListener('close', () => {
        if (dialog.returnValue !== 'confirm') return;
        form.elements.acknowledged.value = '1';
        form.requestSubmit();
    });
});

// Display each school-window announcement once per browser session and deadline.
document.querySelectorAll('[data-window-announcement]').forEach(dialog => {
    const key = 'school-window-' + dialog.dataset.popupKey;
    let seen = false;
    try { seen = sessionStorage.getItem(key) === 'seen'; } catch {}
    const updateDays = () => {
        const counter = dialog.querySelector('[data-days-remaining]');
        const today = new Intl.DateTimeFormat('en-CA', {timeZone:'Africa/Kampala',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
        const part = type => today.find(p => p.type === type).value;
        const todayUtc = Date.UTC(Number(part('year')), Number(part('month')) - 1, Number(part('day')));
        const deadlineUtc = Date.parse(counter.dataset.deadline + 'T00:00:00Z');
        counter.textContent = Math.max(0, Math.round((deadlineUtc - todayUtc) / 86400000));
        if (todayUtc > deadlineUtc) dialog.close();
    };
    updateDays();
    if (!seen) {
        dialog.showModal();
        try { sessionStorage.setItem(key, 'seen'); } catch {}
    }
    dialog.querySelectorAll('[data-close-announcement]').forEach(button => button.addEventListener('click', () => dialog.close()));
    const timer = setInterval(updateDays, 60000);
    dialog.addEventListener('close', () => clearInterval(timer), {once:true});
});

const supportIssueDialog = document.querySelector('[data-whatsapp-support-dialog]');
if (supportIssueDialog) {
    document.querySelector('[data-open-whatsapp-support]')?.addEventListener('click', () => supportIssueDialog.showModal());
    supportIssueDialog.querySelectorAll('[data-close-whatsapp-support]').forEach(button => button.addEventListener('click', () => supportIssueDialog.close()));
    supportIssueDialog.querySelector('[data-whatsapp-issue-form]').addEventListener('submit', event => {
        event.preventDefault();
        const form = event.currentTarget;
        const issue = form.elements.issue.value.trim();
        if (!issue) { form.elements.issue.setCustomValidity('Please describe your issue.'); form.elements.issue.reportValidity(); return; }
        const message = 'MDES Support Request' + (form.dataset.school ? '\nSchool: ' + form.dataset.school : '') + '\n\nIssue: ' + issue;
        window.open('https://wa.me/256705028592?text=' + encodeURIComponent(message), '_blank', 'noopener,noreferrer');
    });
    supportIssueDialog.querySelector('textarea').addEventListener('input', event => event.target.setCustomValidity(''));
}

// Dismiss the client navigation with Escape or a click outside the menu.
const clientNavigation = document.querySelector('.client-header-menu');
clientNavigation?.addEventListener('toggle', () => {
    clientNavigation.querySelector('summary').setAttribute('aria-expanded', String(clientNavigation.open));
});
document.addEventListener('click', event => {
    if (clientNavigation?.open && !clientNavigation.contains(event.target)) clientNavigation.open = false;
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && clientNavigation?.open) {
        clientNavigation.open = false;
        clientNavigation.querySelector('summary').focus();
    }
});

// Bring mobile school-profile validation errors into view.
const confirmationPage = document.querySelector('.mobile-confirmation-page');
if (confirmationPage && window.matchMedia('(max-width: 820px)').matches) {
    const firstError = confirmationPage.querySelector('[aria-invalid="true"], label:has(.school-field-error) select');
    if (firstError) {
        firstError.focus();
        firstError.scrollIntoView({ block: 'center' });
    }
}

// Keep table guidance accurate after resizing, rotating, or changing row contents.
const mobileViewport = window.matchMedia('(max-width: 820px)');
const tableCleanups = [];
const configureMobileTables = () => {
    tableCleanups.splice(0).forEach(cleanup => cleanup());
    if (!mobileViewport.matches) return;
    document.querySelectorAll('.reference-form-page .table-wrap').forEach(wrapper => {
        const table = wrapper.querySelector('table');
        if (!table) return;
        const originalAttributes = ['tabindex', 'role', 'aria-label'].map(name => [name, wrapper.getAttribute(name)]);
        const heading = wrapper.closest('[data-source-table]')?.querySelector('h3');
        wrapper.setAttribute('tabindex', '0');
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', (heading?.textContent || 'Form table') + '. Scroll horizontally to view all fields.');
        const hint = document.createElement('p');
        hint.className = 'mobile-table-swipe-hint';
        wrapper.before(hint);
        const update = () => {
            const overflow = wrapper.scrollWidth > wrapper.clientWidth + 1;
            hint.hidden = !overflow;
            hint.textContent = wrapper.scrollLeft >= wrapper.scrollWidth - wrapper.clientWidth - 2 ? 'Swipe back to see earlier columns' : 'Swipe to see more \u2192';
        };
        const observer = new ResizeObserver(update);
        observer.observe(wrapper);
        observer.observe(table);
        wrapper.addEventListener('scroll', update, { passive: true });
        requestAnimationFrame(update);
        tableCleanups.push(() => {
            observer.disconnect();
            wrapper.removeEventListener('scroll', update);
            hint.remove();
            originalAttributes.forEach(([name,value]) => value === null ? wrapper.removeAttribute(name) : wrapper.setAttribute(name,value));
        });
    });
};
configureMobileTables();
mobileViewport.addEventListener('change', configureMobileTables);

// Only one header menu should be open at a time.
clientNavigation?.addEventListener('toggle', () => {
    if (clientNavigation.open) {
        profileMenu?.classList.remove('is-open');
        profileToggle?.setAttribute('aria-expanded', 'false');
    }
});
profileToggle?.addEventListener('click', () => {
    if (profileMenu?.classList.contains('is-open') && clientNavigation) clientNavigation.open = false;
});
// Mark server-required profile fields without changing desktop behaviour.
const mobileRequiredFields = [...document.querySelectorAll('[data-mobile-required]')].map(field => ({ field, required: field.required }));
const syncRequiredFields = () => mobileRequiredFields.forEach(({field,required}) => { field.required = required || mobileViewport.matches; });
syncRequiredFields();
mobileViewport.addEventListener('change', syncRequiredFields);

// A single compact contact dialog for the registered-school list.
const schoolContactPopup = document.querySelector('#school-contact-popup');
if (schoolContactPopup) {
    let schoolContactTrigger;
    document.querySelectorAll('[data-school-contact]').forEach(button => {
        button.addEventListener('click', () => {
            schoolContactTrigger = button;
            schoolContactPopup.querySelector('[data-contact-school]').textContent = button.dataset.school;
            const head = schoolContactPopup.querySelector('[data-contact-headteacher]');
            head.textContent = button.dataset.headteacher ? 'Headteacher: ' + button.dataset.headteacher : '';
            head.hidden = !button.dataset.headteacher;
            const phone = button.dataset.telephone?.trim() || '';
            const email = button.dataset.email?.trim() || '';
            const phoneLink = schoolContactPopup.querySelector('[data-contact-phone]');
            phoneLink.textContent = phone ? 'Call ' + phone : '';
            phoneLink.hidden = !phone;
            if (phone) phoneLink.href = 'tel:' + phone.replace(/[^\d+]/g, '');
            else phoneLink.removeAttribute('href');
            schoolContactPopup.querySelector('[data-contact-phone-missing]').hidden = Boolean(phone);
            const emailLink = schoolContactPopup.querySelector('[data-contact-email]');
            emailLink.textContent = email;
            emailLink.hidden = !email;
            if (email) emailLink.href = 'mailto:' + email;
            else emailLink.removeAttribute('href');
            schoolContactPopup.querySelector('[data-contact-email-missing]').hidden = Boolean(email);
            const messageLink = schoolContactPopup.querySelector('[data-contact-message]');
            messageLink.hidden = !button.dataset.messageUrl;
            if (button.dataset.messageUrl) messageLink.href = button.dataset.messageUrl;
            else messageLink.removeAttribute('href');
            schoolContactPopup.showModal();
        });
    });
    schoolContactPopup.querySelector('[data-close-school-contact]').addEventListener('click', () => schoolContactPopup.close());
    schoolContactPopup.addEventListener('click', event => {
        const box = schoolContactPopup.getBoundingClientRect();
        if (event.target === schoolContactPopup && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) schoolContactPopup.close();
    });
    schoolContactPopup.addEventListener('close', () => schoolContactTrigger?.focus());
}

// Required school announcements can only be dismissed by submitting acknowledgement.
const requiredAnnouncement = document.querySelector('[data-required-announcement]');
if (requiredAnnouncement) {
    requiredAnnouncement.removeAttribute('open');
    requiredAnnouncement.showModal();
    requiredAnnouncement.addEventListener('cancel', event => event.preventDefault());
}

const announcementSchools = [...document.querySelectorAll('[data-announcement-school]')];
if (announcementSchools.length) {
    const updateAnnouncementCategories = () => {
        const selected = announcementSchools.filter(checkbox => checkbox.checked);
        const hasSelection = selected.length > 0;
        const selectionLabel = document.querySelector('[data-school-selection-label]');
        if (selectionLabel) selectionLabel.textContent = hasSelection ? `${selected.length} school${selected.length === 1 ? '' : 's'} selected` : 'Select schools';
        document.querySelectorAll('[data-announcement-categories] fieldset[data-announcement-category]').forEach(fieldset => {
            fieldset.disabled = hasSelection;
        });
    };
    announcementSchools.forEach(checkbox => checkbox.addEventListener('change', updateAnnouncementCategories));
    updateAnnouncementCategories();
}
const announcementSchoolSearch = document.querySelector('[data-announcement-school-search]');
if (announcementSchoolSearch) {
    announcementSchoolSearch.addEventListener('input', () => {
        const query = announcementSchoolSearch.value.trim().toLocaleLowerCase();
        document.querySelectorAll('.announcement-school-option').forEach(option => {
            option.hidden = !option.textContent.toLocaleLowerCase().includes(query);
        });
    });
}

const schoolDropdown = document.querySelector('[data-school-dropdown]');
if (schoolDropdown) {
    document.addEventListener('click', event => {
        if (!schoolDropdown.contains(event.target)) schoolDropdown.open = false;
    });
    schoolDropdown.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            schoolDropdown.open = false;
            schoolDropdown.querySelector('summary').focus();
        }
    });
}

const correctionDialog = document.querySelector('[data-correction-dialog]');
if (correctionDialog) {
    const correctionTrigger = document.querySelector('[data-open-correction-dialog]');
    correctionTrigger?.addEventListener('click', () => correctionDialog.showModal());
    correctionDialog.querySelectorAll('[data-close-correction-dialog]').forEach(button => {
        button.addEventListener('click', () => correctionDialog.close());
    });
    correctionDialog.addEventListener('close', () => correctionTrigger?.focus());
    if (correctionDialog.dataset.reopen === 'true') correctionDialog.showModal();
}

const submittedPage = document.querySelector('.submitted-record-page');
if (submittedPage) {
    const summary = submittedPage.querySelector('.submitted-record-summary');
    const topbar = document.querySelector('.topbar');
    const sectionLinks = [...submittedPage.querySelectorAll('.submitted-record-nav a')];
    const sections = [...submittedPage.querySelectorAll('.submitted-data-section')];
    const resizeSummary = () => {
        const topbarPosition = topbar ? getComputedStyle(topbar).position : '';
        const offset = ['fixed', 'sticky'].includes(topbarPosition) ? topbar.getBoundingClientRect().height : 0;
        submittedPage.style.setProperty('--submitted-sticky-top', `${offset}px`);
        submittedPage.style.setProperty('--submitted-summary-height', `${summary?.getBoundingClientRect().height || 90}px`);
    };
    let sectionUpdateQueued = false;
    const highlightSubmittedSection = () => {
        const boundary = (summary?.getBoundingClientRect().bottom || 0) + 40;
        let current = sections[0];
        sections.forEach(section => {
            if (section.getBoundingClientRect().top <= boundary) current = section;
        });
        sectionLinks.forEach(link => {
            if (current && link.hash === `#${current.id}`) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
        sectionUpdateQueued = false;
    };
    window.addEventListener('scroll', () => {
        if (!sectionUpdateQueued) {
            sectionUpdateQueued = true;
            requestAnimationFrame(highlightSubmittedSection);
        }
    }, { passive: true });
    window.addEventListener('resize', resizeSummary);
    if (window.ResizeObserver && summary) {
        const summaryObserver = new ResizeObserver(resizeSummary);
        summaryObserver.observe(summary);
        if (topbar) summaryObserver.observe(topbar);
    }
    resizeSummary();
    highlightSubmittedSection();
}

// Reload restored authenticated pages so the server rechecks the session after logout.
window.addEventListener('pageshow', event => {
    if (event.persisted) window.location.reload();
});

// Keep unfinished statistical sections on the server, with a device backup until acknowledged.
document.querySelectorAll('form[data-autosave-url]').forEach(form => {
    const storageKey = 'mdes-form-draft:' + form.dataset.autosaveKey;
    const revisionField = form.elements.draft_revision;
    const indicators = [...form.querySelectorAll('[data-autosave-status]')];
    const state = protectedForms.get(form);
    const entries = () => [...new FormData(form).entries()].filter(([name]) => name.startsWith('data['));
    let acknowledged = JSON.stringify(entries());
    let pending = null;
    let timer;
    let retryTimer;
    let conflict = false;
    let recovery = null;
    let allowSubmit = false;
    let deviceBackupAvailable = true;
    const status = (message, tone = '') => indicators.forEach(indicator => {
        indicator.textContent = message;
        indicator.dataset.state = tone;
    });
    const backup = () => {
        const snapshot = { entries: entries(), revision: Number(revisionField.value), updated: Date.now() };
        try { localStorage.setItem(storageKey, JSON.stringify(snapshot)); } catch { deviceBackupAvailable = false; }
        return snapshot;
    };
    const clearBackup = () => { try { localStorage.removeItem(storageKey); } catch {} };
    const markSaved = () => {
        if (state) { state.initial = formFingerprint(form); state.dirty = false; state.submitting = false; }
    };
    const restore = snapshot => {
        form.querySelectorAll('[data-repeatable-table]').forEach(table => {
            const key = table.dataset.sourceTable;
            const body = table.querySelector('[data-source-rows]');
            const prototype = body?.querySelector('tr')?.cloneNode(true);
            if (!prototype) return;
            const indices = [...new Set(snapshot.entries.map(([name]) => name.startsWith(`data[${key}][`) ? name.slice(`data[${key}][`.length).split(']')[0] : null).filter(index => /^\d+$/.test(index ?? '')))];
            body.replaceChildren();
            (indices.length ? indices : ['0']).forEach(index => {
                const row = prototype.cloneNode(true);
                row.querySelectorAll('.source-inline-error').forEach(error => error.remove());
                row.querySelectorAll('[name]').forEach(field => { field.name = field.name.replace(/\[\d+\]/, `[${index}]`); });
                row.querySelector('th').textContent = Number(index) + 1;
                body.append(row);
            });
        });
        const values = new Map();
        snapshot.entries.forEach(([name, value]) => values.set(name, [...(values.get(name) || []), value]));
        [...form.elements].filter(field => !field.disabled && field.name?.startsWith('data[')).forEach(field => {
            if (['checkbox', 'radio'].includes(field.type)) field.checked = (values.get(field.name) || []).includes(field.value);
            else field.value = values.get(field.name)?.[0] ?? '';
        });
        form.dispatchEvent(new Event('formstructurechange'));
    };
    const save = async () => {
        clearTimeout(timer);
        clearTimeout(retryTimer);
        if (pending) { if (!await pending) return false; return save(); }
        if (conflict || recovery) return false;
        const snapshot = backup();
        const fingerprint = JSON.stringify(snapshot.entries);
        if (fingerprint === acknowledged) { clearBackup(); markSaved(); return true; }
        status('Saving your progress…', 'saving');
        const body = new FormData();
        body.set('_token', form.elements._token.value);
        body.set('revision', revisionField.value);
        snapshot.entries.forEach(([name, value]) => body.append(name, value));
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        pending = (async () => {
            try {
                const response = await fetch(form.dataset.autosaveUrl, { method: 'POST', body, signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    if (response.status === 409) {
                        conflict = true;
                        status('This section changed in another tab. Your entries are kept on this device. Reload to review them.', 'error');
                    } else if ([401, 403, 419].includes(response.status)) {
                        conflict = true;
                        status('Saving is unavailable. Your entries are kept on this device. Sign in again or check whether the form is locked.', 'error');
                    } else {
                        status(deviceBackupAvailable ? 'Not saved to the server yet. Keep this page open; your entries are backed up on this device.' : 'Not saved yet, and device backup is unavailable. Keep this page open and reconnect.', 'error');
                        retryTimer = setTimeout(save, 10000);
                    }
                    return false;
                }
                const result = await response.json();
                revisionField.value = result.revision;
                acknowledged = fingerprint;
                if (JSON.stringify(entries()) === fingerprint) {
                    clearBackup();
                    markSaved();
                    status(result.valid ? 'Saved automatically' : 'Draft saved. Unfinished entries will need checking before submission.', 'saved');
                } else {
                    backup();
                    timer = setTimeout(save, 250);
                }
                return true;
            } catch {
                status(deviceBackupAvailable ? 'Connection interrupted. Your entries are backed up on this device; saving will retry.' : 'Connection interrupted and device backup is unavailable. Keep this page open; saving will retry.', 'error');
                retryTimer = setTimeout(save, 10000);
                return false;
            } finally { clearTimeout(timeout); pending = null; }
        })();
        return pending;
    };
    const changed = () => {
        backup();
        if (state) { state.dirty = JSON.stringify(entries()) !== acknowledged; state.submitting = false; }
        if (conflict || recovery) return;
        status('Changes waiting to save…', 'saving');
        clearTimeout(timer);
        timer = setTimeout(save, 800);
    };
    ['input', 'change', 'formstructurechange'].forEach(event => form.addEventListener(event, changed));
    window.addEventListener('online', () => save());
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') save(); });
    window.addEventListener('pagehide', () => {
        if (state?.submitting || JSON.stringify(entries()) === acknowledged) return;
        const snapshot = backup();
        if (pending || conflict || recovery) return;
        const body = new FormData();
        body.set('_token', form.elements._token.value);
        body.set('revision', revisionField.value);
        snapshot.entries.forEach(([name, value]) => body.append(name, value));
        fetch(form.dataset.autosaveUrl, { method: 'POST', body, credentials: 'same-origin', keepalive: true, headers: { Accept: 'application/json' } }).catch(() => {});
    });
    document.addEventListener('click', async event => {
        const link = event.target.closest('a[href]');
        if (event.defaultPrevented || !link || link.target === '_blank' || link.hasAttribute('download') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const target = new URL(link.href, location.href);
        if (target.origin !== location.origin || (target.pathname === location.pathname && target.search === location.search && target.hash)) return;
        if (JSON.stringify(entries()) === acknowledged && !pending && !recovery) return;
        event.preventDefault();
        if (await save() || window.confirm(deviceBackupAvailable ? 'Your changes have not reached the server. Leave anyway? Unsynced entries will remain on this device.' : 'Your changes have not been saved or backed up. Leaving now will lose them. Leave anyway?')) {
            if (state) state.submitting = true;
            location.assign(link.href);
        }
    });
    // Drain any outstanding autosave before Save draft, Next, logout, or final submission.
    document.addEventListener('submit', async event => {
        if (allowSubmit || event.defaultPrevented) return;
        const target = event.target;
        if (!(target instanceof HTMLFormElement)) return;
        event.preventDefault();
        const submitter = event.submitter;
        if (target === form) {
            if (pending) await pending;
            if (conflict || recovery) { if (state) state.submitting = false; return; }
            backup();
        } else if (!await save()) { if (state) state.submitting = false; return; }
        clearTimeout(timer);
        clearTimeout(retryTimer);
        if (state) state.submitting = true;
        allowSubmit = true;
        target.requestSubmit(submitter);
        allowSubmit = false;
    });
    try {
        const snapshot = JSON.parse(localStorage.getItem(storageKey) || 'null');
        if (snapshot && Array.isArray(snapshot.entries) && Date.now() - snapshot.updated < 7 * 86400000) {
            if (JSON.stringify(snapshot.entries) === acknowledged) clearBackup();
            else if (snapshot.revision === Number(revisionField.value)) {
                restore(snapshot);
                status('Restored your unsaved progress from this device. Saving…', 'saving');
            } else {
                recovery = snapshot;
                status('This device has unsynced entries, while the server has a newer version. Choose which version to keep.', 'error');
                const actions = document.createElement('div');
                actions.className = 'autosave-recovery-actions';
                [['Restore device draft', true], ['Keep server version', false]].forEach(([label, useBackup]) => {
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = 'button'; button.textContent = label;
                    button.addEventListener('click', () => {
                        const previous = recovery; recovery = null; actions.remove();
                        if (useBackup) { restore(previous); save(); }
                        else { clearBackup(); status('Server version kept', 'saved'); }
                    });
                    actions.append(button);
                });
                indicators[0].after(actions);
            }
        } else clearBackup();
    } catch { status('Automatic saving is active. Device backup is unavailable in this browser.'); }
});
