document.documentElement.classList.add('js');
const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

const hero = document.querySelector('[data-catalog-hero]');
if (hero) {
    const panels = [...hero.querySelectorAll('[data-hero-panel]')];
    let current = 0;
    const select = (index) => {
        current = (index + panels.length) % panels.length;
        panels.forEach((panel, position) => {
            panel.hidden = position !== current;
            panel.classList.toggle('destination-enter', position === current);
        });
        hero.querySelectorAll('[data-hero-background]').forEach((image) => {
            image.classList.toggle('is-active', Number(image.dataset.heroBackground) === current);
        });
        hero.querySelectorAll('[data-hero-select]').forEach((button) => {
            // Show only the next two trips after the current one, in carousel order.
            const offset = (Number(button.dataset.heroSelect) - current + panels.length) % panels.length;
            button.hidden = offset === 0 || offset > 2;
            button.style.order = String(offset);
        });
        hero.querySelector('[data-hero-counter]').textContent = String(current + 1).padStart(2, '0');
        hero.querySelector('[data-hero-status]').textContent = `${current + 1}. od ${panels.length}: ${panels[current].querySelector('h1').textContent}.`;
    };
    hero.addEventListener('click', (event) => {
        const step = event.target.closest('[data-hero-step]');
        const card = event.target.closest('[data-hero-select]');
        if (step) select(current + Number(step.dataset.heroStep));
        if (card) {
            select(Number(card.dataset.heroSelect));
            hero.querySelector('[data-hero-step="1"]').focus({ preventScroll: true });
        }
    });
    hero.addEventListener('keydown', (event) => {
        if (event.altKey || event.ctrlKey || event.metaKey || !['ArrowLeft', 'ArrowRight'].includes(event.key) || panels.length < 2) return;
        event.preventDefault();
        select(current + (event.key === 'ArrowRight' ? 1 : -1));
        hero.querySelector('[data-hero-step="1"]').focus({ preventScroll: true });
    });
}

let whatsappObserver;
function enhanceSurface(root = document) {
    root.querySelectorAll('img[data-catalog-image]').forEach((image) => {
        const showPlaceholder = () => {
            const placeholder = document.createElement('div');
            placeholder.className = 'card-image-placeholder';
            placeholder.textContent = 'Maestro Travel';
            placeholder.setAttribute('role', 'img');
            placeholder.setAttribute('aria-label', image.alt || 'Fotografija nije dostupna');
            image.replaceWith(placeholder);
        };
        if (image.complete && image.naturalWidth === 0) showPlaceholder();
        else image.addEventListener('error', showPlaceholder, { once: true });
    });
    root.querySelectorAll('.term-options, [data-hotel-options], [data-trip-options]').forEach((group) => {
        const buttons = [...group.querySelectorAll('[role="radio"]:not(:disabled)')];
        const selected = buttons.find((button) => button.getAttribute('aria-checked') === 'true') || buttons[0];
        buttons.forEach((button) => { button.tabIndex = button === selected ? 0 : -1; });
    });
    whatsappObserver?.disconnect();
    const floating = document.getElementById('whatsapp-floating');
    if (floating && typeof IntersectionObserver !== 'undefined') {
        const visible = new Set();
        floating.classList.remove('is-redundant');
        whatsappObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => entry.intersectionRatio >= .6 ? visible.add(entry.target) : visible.delete(entry.target));
            floating.classList.toggle('is-redundant', visible.size > 0 && document.activeElement !== floating);
        }, { threshold: [0, .6, 1] });
        document.querySelectorAll('[data-whatsapp-context]').forEach((link) => whatsappObserver.observe(link));
    }
}
enhanceSurface();

let request;
let searchTimer;
async function navigateCatalog(url, { detail = false, replace = false, focusTerm = null } = {}) {
    request?.abort();
    const controller = new AbortController();
    request = controller;
    const selector = detail ? '.catalog-detail' : '#ponude';
    const current = document.querySelector(selector);
    const focusedControl = !detail && document.activeElement?.closest('.catalog-tools') ? document.activeElement.id : null;
    const restoreFilterFocus = !detail && current.contains(document.activeElement);
    const position = { left: window.scrollX, top: window.scrollY };
    const listScroll = current.querySelector('.hotel-available-options, [data-departure-list]')?.scrollTop || 0;
    current.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error('Ponuda nije dostupna.');
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const replacement = page.querySelector(selector);
        if (controller.signal.aborted) return;
        if (!replacement) throw new Error('Pregled više nije dostupan.');
        current.replaceWith(replacement);
        const incomingWhatsapp = page.getElementById('whatsapp-floating');
        const floating = document.getElementById('whatsapp-floating');
        if (floating && incomingWhatsapp) floating.href = incomingWhatsapp.href;
        else if (floating && !incomingWhatsapp) {
            floating.remove();
            document.body.classList.remove('has-whatsapp');
        } else if (incomingWhatsapp) {
            document.body.append(incomingWhatsapp);
            document.body.classList.add('has-whatsapp');
        }
        document.title = page.title;
        history[replace ? 'replaceState' : 'pushState']({ catalogNavigation: true }, '', url);
        enhanceSurface(replacement);
        const list = replacement.querySelector('.hotel-available-options, [data-departure-list]');
        if (list && (focusTerm?.startsWith('hotel-rate-') || list.matches('[data-departure-list]'))) list.scrollTop = listScroll;
        if (restoreFilterFocus) {
            const target = document.getElementById(focusedControl) || replacement.querySelector('.catalog-chip, .catalog-types [aria-current]');
            target?.focus({ preventScroll: true });
        }
        if (focusTerm) {
            const button = replacement.querySelector(`[data-term-id="${CSS.escape(focusTerm)}"]:not(:disabled)`)
                || replacement.querySelector('[role="radio"][aria-checked="true"]');
            button?.focus({ preventScroll: true });
            if (list && button && list.contains(button)) {
                const top = button.getBoundingClientRect().top - list.getBoundingClientRect().top + list.scrollTop;
                if (top < list.scrollTop) list.scrollTop = top;
                else if (top + button.offsetHeight > list.scrollTop + list.clientHeight) list.scrollTop = top + button.offsetHeight - list.clientHeight;
            }
            const status = replacement.querySelector('#term-selection-status');
            if (status) status.textContent = replacement.dataset.selectionAnnouncement || replacement.querySelector('.selected-term-summary')?.textContent.trim()
                || replacement.querySelector('.booking')?.textContent.trim();
        }
        window.scrollTo({ ...position, behavior: 'instant' });
    } catch (error) {
        if (error.name !== 'AbortError') window.location.assign(url);
    } finally {
        current.removeAttribute('aria-busy');
    }
}

function searchUrl(form) {
    const url = new URL(form.action);
    const params = new URLSearchParams(new FormData(form));
    for (const [name, value] of [...params]) {
        if (!value.trim()) params.delete(name);
    }
    url.search = params.toString();
    return url;
}
document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-catalog-filter]');
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    clearTimeout(searchTimer);
    const url = new URL(link.href);
    if (link.hasAttribute('data-offer-type') || link.hasAttribute('data-category')) {
        const form = document.querySelector('[data-catalog-filters]');
        const params = searchUrl(form).searchParams;
        if (link.hasAttribute('data-category')) params.set('category', link.dataset.category);
        if (link.hasAttribute('data-offer-type') && link.dataset.offerType !== params.get('type')) {
            params.set('type', link.dataset.offerType);
            params.delete('transport');
            params.delete('amenities[]');
        }
        url.search = params.toString();
    }
    navigateCatalog(url);
});
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.matches('[data-catalog-filters]')) {
        event.preventDefault();
        clearTimeout(searchTimer);
        navigateCatalog(searchUrl(form));
    } else if (form.matches('[data-term-form]')) {
        event.preventDefault();
        navigateCatalog(searchUrl(form), { detail: true, replace: true, focusTerm: form.dataset.focusTerm || form.querySelector('[data-term-id]').dataset.termId });
    }
});
document.addEventListener('change', (event) => {
    if (!event.target.matches('[data-catalog-filters] select, [data-catalog-filters] input[type="checkbox"]')) return;
    clearTimeout(searchTimer);
    navigateCatalog(searchUrl(event.target.form));
});
document.addEventListener('input', (event) => {
    if (!event.target.matches('[data-catalog-filters] input[name="q"]')) return;
    clearTimeout(searchTimer);
    request?.abort();
    const form = event.target.form;
    searchTimer = setTimeout(() => navigateCatalog(searchUrl(form), { replace: true }), 300);
});
document.addEventListener('keydown', (event) => {
    const group = event.target.closest('.term-options, [data-hotel-options], [data-trip-options]');
    if (!group || event.altKey || event.ctrlKey || event.metaKey || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const buttons = [...group.querySelectorAll('[role="radio"]:not(:disabled)')];
    if (!buttons.length) return;
    const current = buttons.indexOf(event.target.closest('[role="radio"]'));
    const index = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1
        : (current + (['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1) + buttons.length) % buttons.length;
    buttons[index].focus({ preventScroll: true });
    buttons[index].form.requestSubmit(buttons[index]);
});
window.addEventListener('popstate', () => window.location.reload());

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-hotel-auto-submit]')) event.target.form.requestSubmit();
});

document.addEventListener('click', async (event) => {
    if (!event.target.closest('[data-copy-message]')) return;
    const text = document.getElementById('contact-message');
    const status = document.getElementById('copy-status');
    try {
        await navigator.clipboard.writeText(text.value);
        status.textContent = 'Podaci ponude su kopirani. Zalijepite ih u Viber razgovor.';
    } catch {
        text.focus();
        text.select();
        status.textContent = 'Automatsko kopiranje nije dostupno. Kopirajte označeni tekst.';
    }
});

// Instagram's embed reports its content height; size the matching iframe so photo and caption fit.
window.addEventListener('message', (event) => {
    if (!/^https:\/\/(www\.)?instagram\.com$/.test(event.origin)) return;
    let data = event.data;
    if (typeof data === 'string') {
        try { data = JSON.parse(data); } catch { return; }
    }
    const height = Number(data?.details?.height);
    if (data?.type !== 'MEASURE' || !Number.isFinite(height) || height < 100 || height > 3000) return;
    document.querySelectorAll('[data-instagram-embed]').forEach((frame) => {
        // Long captions scroll inside the embed instead of stretching the whole row.
        if (frame.contentWindow === event.source) frame.style.height = `${Math.min(Math.ceil(height), 820)}px`;
    });
});

const posts = document.getElementById('instagram-posts');
if (posts) {
    const items = [...posts.children];
    const previous = document.getElementById('instagram-prev');
    const next = document.getElementById('instagram-next');
    const counter = document.getElementById('instagram-counter');
    let current = 0;
    let pending = null;
    let settleTimer;
    const update = (index) => {
        current = Math.max(0, Math.min(items.length - 1, index));
        previous.disabled = current === 0;
        next.disabled = current === items.length - 1;
        counter.textContent = `Objava ${current + 1} od ${items.length}`;
    };
    const go = (offset) => {
        pending = Math.max(0, Math.min(items.length - 1, (pending ?? current) + offset));
        posts.scrollTo({ left: items[pending].offsetLeft - items[0].offsetLeft, behavior: reducedMotion() ? 'instant' : 'smooth' });
        update(pending);
    };
    previous.addEventListener('click', () => go(-1));
    next.addEventListener('click', () => go(1));
    posts.addEventListener('scroll', () => {
        const step = items.length > 1 ? items[1].offsetLeft - items[0].offsetLeft : 0;
        if (!step) return;
        if (pending === null) update(Math.round(posts.scrollLeft / step));
        clearTimeout(settleTimer);
        settleTimer = setTimeout(() => { pending = null; update(Math.round(posts.scrollLeft / step)); }, 120);
    }, { passive: true });
    posts.addEventListener('pointerdown', () => { pending = null; }, { passive: true });
    window.addEventListener('resize', () => { pending = null; update(0); posts.scrollLeft = 0; });
    posts.querySelectorAll('img').forEach((image) => {
        const fallback = () => {
            image.hidden = true;
            image.parentElement.classList.add('is-unavailable');
            image.parentElement.querySelector('.instagram-media-fallback').hidden = false;
        };
        if (image.complete && image.naturalWidth === 0) fallback();
        else image.addEventListener('error', fallback, { once: true });
    });
}
