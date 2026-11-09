const articleSelector = '[data-wiki-article]';
const metadataSelector = [
    'meta[name="description"]',
    'meta[name="keywords"]',
    'meta[name="robots"]',
    'meta[property^="og:"]',
    'meta[name^="twitter:"]',
    'link[rel="canonical"]',
    'link[rel="alternate"][hreflang]',
    'script[type="application/ld+json"]',
].join(', ');

function pageAddress(url) {
    return `${url.origin}${url.pathname}${url.search}`;
}

let displayed = pageAddress(new URL(window.location.href));
let pending;

async function navigate(current, url, fromHistory = false) {
    pending?.abort();
    const controller = new AbortController();
    pending = controller;
    current.setAttribute('aria-busy', 'true');
    const timeout = window.setTimeout(() => controller.abort(), 15000);

    try {
        const response = await fetch(url.href, {
            mode: 'same-origin',
            credentials: 'same-origin',
            headers: { Accept: 'text/html' },
            cache: 'no-store',
            signal: controller.signal,
        });
        if (!response.ok || response.redirected || pageAddress(new URL(response.url)) !== pageAddress(url)) {
            throw new Error('Wiki article unavailable');
        }
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const article = page.querySelector(articleSelector);
        if (!article || !current.isConnected || page.documentElement.lang !== document.documentElement.lang) {
            throw new Error('Wiki article view unavailable');
        }
        if (pending !== controller) {
            return;
        }
        controller.signal.throwIfAborted();

        const next = document.importNode(article, true);
        next.querySelectorAll('script').forEach((script) => script.remove());
        if (!fromHistory) {
            window.history.pushState(null, '', url.href);
        }
        current.replaceWith(next);
        displayed = pageAddress(url);
        document.title = page.title;
        document.head.querySelectorAll(metadataSelector).forEach((element) => element.remove());
        for (const element of page.head.querySelectorAll(metadataSelector)) {
            document.head.append(document.importNode(element, true));
        }
        next.dispatchEvent(new CustomEvent('kraft:content-added', { bubbles: true }));

        const heading = next.querySelector('article h1');
        heading?.focus({ preventScroll: true });
        (next.querySelector(':target') || heading)?.scrollIntoView({ block: 'start' });
    } catch {
        if (pending === controller) {
            if (fromHistory) {
                window.location.reload();
            } else {
                window.location.assign(url.href);
            }
        }
    } finally {
        window.clearTimeout(timeout);
        if (pending === controller) {
            pending = undefined;
            current.removeAttribute('aria-busy');
        }
    }
}

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[data-wiki-article-link]');
    const current = link?.closest(articleSelector);
    if (
        !current ||
        event.defaultPrevented ||
        event.button !== 0 ||
        event.ctrlKey ||
        event.metaKey ||
        event.shiftKey ||
        event.altKey ||
        link.hasAttribute('download') ||
        (link.target && link.target.toLowerCase() !== '_self')
    ) {
        return;
    }
    const url = new URL(link.href);
    if (url.origin !== window.location.origin || url.hash || pageAddress(url) === displayed) {
        return;
    }

    event.preventDefault();
    void navigate(current, url);
});

window.addEventListener('popstate', () => {
    const current = document.querySelector(articleSelector);
    if (!current) {
        return;
    }
    const url = new URL(window.location.href);
    if (pageAddress(url) === displayed) {
        const controller = pending;
        pending = undefined;
        controller?.abort();
        current.removeAttribute('aria-busy');
        return;
    }

    void navigate(current, url, true);
});
