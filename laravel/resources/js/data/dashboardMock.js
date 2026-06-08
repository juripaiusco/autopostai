/* FaPer3 Dashboard — mock data (ultimi 90 giorni / 12 mesi)
   Ported from the Claude Design handoff (dashboard-data.jsx). Replace with
   real Inertia props once Post/Channel models exist in v2. */

export const MONTHLY_DATA = [
    { month: 'Lug', views: 18200, comments: 89 },
    { month: 'Ago', views: 15800, comments: 74 },
    { month: 'Set', views: 22400, comments: 112 },
    { month: 'Ott', views: 26700, comments: 143 },
    { month: 'Nov', views: 24100, comments: 128 },
    { month: 'Dic', views: 19800, comments: 95 },
    { month: 'Gen', views: 23500, comments: 141 },
    { month: 'Feb', views: 28900, comments: 178 },
    { month: 'Mar', views: 31200, comments: 210 },
    { month: 'Apr', views: 35800, comments: 267 },
    { month: 'Mag', views: 38400, comments: 298 },
    { month: 'Giu', views: 41200, comments: 334 },
];

export const CHANNEL_METRICS = [
    { id: 'facebook', views: 12840, unique: 4210, comments: 234, posts: 18 },
    { id: 'instagram', views: 18650, unique: 7820, comments: 892, posts: 22 },
    { id: 'linkedin', views: 6430, unique: 3640, comments: 156, posts: 12 },
    { id: 'wordpress', views: 4190, unique: 2870, comments: 67, posts: 8 },
    { id: 'newsletter', views: 3280, unique: 3120, comments: 0, posts: 6 },
];

export const GLOBAL_METRICS = {
    views: CHANNEL_METRICS.reduce((s, c) => s + c.views, 0),
    unique: 20430, // overlap tra canali rimosso
    comments: CHANNEL_METRICS.reduce((s, c) => s + c.comments, 0),
    posts: CHANNEL_METRICS.reduce((s, c) => s + c.posts, 0),
};

export const RECENT_POSTS = [
    { id: 10, title: 'Promo estate: menù mare', channels: ['facebook', 'instagram'], status: 'published', date: '01 giu 2026', views: 4280, comments: 47, thumb: 'linear-gradient(135deg,#5b7290,#3f5470)' },
    { id: 9, title: 'Nuovi orari estivi del negozio', channels: ['facebook', 'wordpress', 'newsletter'], status: 'scheduled', date: '14 giu 2026', views: 0, comments: 0, thumb: 'linear-gradient(135deg,#6b96b3,#48708c)' },
    { id: 8, title: 'Dietro le quinte: come nasce il pane', channels: ['instagram', 'linkedin'], status: 'published', date: '28 mag 2026', views: 3190, comments: 38, thumb: 'linear-gradient(135deg,#5f8782,#456661)' },
    { id: 7, title: 'Recensione cliente del mese', channels: ['facebook', 'instagram', 'linkedin'], status: 'published', date: '20 mag 2026', views: 5640, comments: 82, thumb: 'linear-gradient(135deg,#6e7494,#4d5270)' },
    { id: 6, title: 'Offerta lancio newsletter giugno', channels: ['newsletter'], status: 'error', date: '—', views: 0, comments: 0, thumb: 'linear-gradient(135deg,#9b7d86,#6f5860)' },
    { id: 5, title: 'Come pubblicare in modo costante', channels: ['wordpress', 'linkedin'], status: 'done', date: '15 mag 2026', views: 2870, comments: 24, thumb: 'linear-gradient(135deg,#7c8a99,#566270)' },
    { id: 4, title: 'Benvenuti sul nostro blog!', channels: ['wordpress', 'facebook'], status: 'done', date: '08 mag 2026', views: 3420, comments: 31, thumb: 'linear-gradient(135deg,#5f8595,#41626f)' },
    { id: 3, title: 'Speciale Festa della Mamma', channels: ['facebook', 'instagram', 'newsletter'], status: 'published', date: '11 mag 2026', views: 7180, comments: 104, thumb: 'linear-gradient(135deg,#9a8b7d,#6f6055)' },
    { id: 2, title: '5 consigli per un profilo LinkedIn', channels: ['linkedin'], status: 'done', date: '02 mag 2026', views: 4390, comments: 67, thumb: 'linear-gradient(135deg,#566b86,#3a4c63)' },
    { id: 1, title: 'Report mensile: aprile 2026', channels: ['newsletter', 'wordpress'], status: 'done', date: '28 apr 2026', views: 2140, comments: 18, thumb: 'linear-gradient(135deg,#6b7686,#4a5563)' },
];

export const CH_CONFIG = {
    facebook: { label: 'Facebook', color: '#1877f2' },
    instagram: { label: 'Instagram', color: '#e4405f' },
    linkedin: { label: 'LinkedIn', color: '#0a66c2' },
    wordpress: { label: 'WordPress', color: '#21759b' },
    newsletter: { label: 'Newsletter', color: '#6b7280' },
};

export function fmtNum(n) {
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
    return String(n);
}

/* Sequential blue scale (darkest = largest) — replaces loud per-platform
   brand colors in data-viz so the dashboard reads with one confident accent. */
export const CH_SCALE = ['#0c4a6e', '#0369a1', '#0284c7', '#38bdf8', '#7dd3fc'];

export const CH_RANKED_COLOR = (() => {
    const sorted = [...CHANNEL_METRICS].sort((a, b) => b.views - a.views);
    const map = {};
    sorted.forEach((c, i) => { map[c.id] = CH_SCALE[Math.min(i, CH_SCALE.length - 1)]; });
    return map;
})();
