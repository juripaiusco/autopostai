// Laravel valida il CSRF anche via header X-XSRF-TOKEN contro il cookie
// XSRF-TOKEN (leggibile da JS, non httpOnly) — utile per le POST via fetch()
// nativo che non passano dal router di Inertia (che gestisce il CSRF da solo).
export function csrfHeader() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {};
}
