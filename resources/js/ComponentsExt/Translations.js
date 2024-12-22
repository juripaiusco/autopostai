export function __(key, replacements = {}) {
    let translation = window._translations[key] || key;

    return translation;
}
