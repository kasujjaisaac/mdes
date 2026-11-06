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
const formFingerprint = (form) => JSON.stringify(Array.from(new FormData(form).entries()).map(([key,value]) => [key,typeof value === 'string' ? value : value.name]));
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

// Read the whole announcement without following the moving text.
document.querySelectorAll('[data-announcement-toggle]').forEach((button,index) => {
    const strip = button.closest('.submission-window-strip');
    const fullNotice = strip.nextElementSibling;
    if (!fullNotice?.classList.contains('mobile-announcement-full')) return;
    fullNotice.id = 'mobile-notice-' + index;
    button.setAttribute('aria-controls', fullNotice.id);
    const closeNotice = () => {
        fullNotice.hidden = true;
        strip.classList.remove('is-notice-open');
        button.setAttribute('aria-expanded', 'false');
        button.textContent = 'Read notice';
    };
    button.addEventListener('click', () => {
        const open = button.getAttribute('aria-expanded') !== 'true';
        fullNotice.hidden = !open;
        strip.classList.toggle('is-notice-open',open);
        button.setAttribute('aria-expanded', String(open));
        button.textContent = open ? 'Close notice' : 'Read notice';
    });
    mobileViewport.addEventListener('change', closeNotice);
});

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
