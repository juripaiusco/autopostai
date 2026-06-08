// Smooth bezier path through a list of [x, y] points — shared by sparklines
// and the dual-axis chart so curves read consistently across the dashboard.
export function bezierPath(pts) {
    if (pts.length < 2) return '';
    return pts
        .map((pt, i) => {
            if (i === 0) return `M${pt[0]},${pt[1]}`;
            const prev = pts[i - 1];
            const cpx = (prev[0] + pt[0]) / 2;
            return `C${cpx},${prev[1]} ${cpx},${pt[1]} ${pt[0]},${pt[1]}`;
        })
        .join(' ');
}
