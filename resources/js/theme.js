// Light/dark switching. `darkMode: 'class'` in tailwind.config.js means every
// `dark:` utility across the app is gated on a `dark` class on <html> — this
// is what sets it, rather than leaving it to `prefers-color-scheme` alone.
const STORAGE_KEY = 'theme'; // 'light' | 'dark'

function prefersDark() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function apply(isDark) {
    document.documentElement.classList.toggle('dark', isDark);
}

export function currentTheme() {
    const stored = typeof window !== 'undefined' ? window.localStorage.getItem(STORAGE_KEY) : null;
    if (stored === 'light' || stored === 'dark') return stored;
    return prefersDark() ? 'dark' : 'light';
}

// Called both from the inline <head> script (before Vue mounts, to avoid a
// flash of the wrong theme) and from app.js (so Vue-rendered UI, e.g. the nav
// toggle button, starts in sync).
export function initTheme() {
    apply(currentTheme() === 'dark');
}

export function toggleTheme() {
    const next = currentTheme() === 'dark' ? 'light' : 'dark';
    window.localStorage.setItem(STORAGE_KEY, next);
    apply(next === 'dark');
    return next;
}
