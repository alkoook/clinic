const themeStorageKey = 'clinic-theme';

function storedTheme() {
    try {
        const value = window.localStorage.getItem(themeStorageKey);

        return value === 'light' || value === 'dark' ? value : null;
    } catch {
        return null;
    }
}

function currentTheme() {
    const documentTheme = document.documentElement.dataset.theme;

    return storedTheme()
        ?? (documentTheme === 'light' || documentTheme === 'dark' ? documentTheme : null)
        ?? 'light';
}

function synchronizeThemeControls(theme) {
    document.querySelectorAll('[data-theme-toggle]').forEach((control) => {
        const isDark = theme === 'dark';
        control.setAttribute('aria-pressed', String(isDark));
        control.setAttribute('aria-label', isDark ? 'تفعيل المظهر الفاتح' : 'تفعيل المظهر الداكن');
    });
}

function applyTheme(theme, persist = false) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = theme;

    if (persist) {
        try {
            window.localStorage.setItem(themeStorageKey, theme);
        } catch {
            // The visual preference still applies when storage is unavailable.
        }
    }

    synchronizeThemeControls(theme);
}

function toggleTheme() {
    applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark', true);
}

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-theme-toggle]')) {
        toggleTheme();
    }
});

document.addEventListener('DOMContentLoaded', () => applyTheme(currentTheme()));
document.addEventListener('livewire:navigate', () => applyTheme(currentTheme()));
document.addEventListener('livewire:navigated', () => applyTheme(currentTheme()));

window.addEventListener('storage', (event) => {
    if (event.key === themeStorageKey && (event.newValue === 'light' || event.newValue === 'dark')) {
        applyTheme(event.newValue);
    }
});
