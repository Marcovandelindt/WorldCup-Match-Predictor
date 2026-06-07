document.addEventListener('DOMContentLoaded', () => {
    buildPointsSvg();
});

function buildPointsSvg() {
    const svg = document.getElementById('chartPoints');
    if (!svg) return;

    const raw = window.statsChartData;
    if (!raw) return;

    const labels = raw.map(d => d.label);
    const pts    = raw.map(d => d.points);
    const cum    = raw.map(d => d.cumulative);

    const W = 640, H = 260, padL = 34, padR = 46, padT = 16, padB = 30;
    const plotW = W - padL - padR;
    const plotH = H - padT - padB;
    const yMax  = Math.max(...pts, 10) * 1.15;
    const cMax  = Math.max(...cum, 10) * 1.1;
    const n     = pts.length;

    const X   = i => padL + (i / (n - 1)) * plotW;
    const Y   = v => padT + plotH * (1 - v / yMax);
    const YC  = v => padT + plotH * (1 - v / cMax);
    const NS  = 'http://www.w3.org/2000/svg';
    const el  = (t, a) => {
        const e = document.createElementNS(NS, t);
        for (const k in a) e.setAttribute(k, a[k]);
        return e;
    };

    // Gradient
    const defs = el('defs', {});
    const lg   = el('linearGradient', { id: 'areaG', x1: 0, y1: 0, x2: 0, y2: 1 });
    lg.appendChild(el('stop', { offset: '0%', 'stop-color': '#16c172', 'stop-opacity': .32 }));
    lg.appendChild(el('stop', { offset: '100%', 'stop-color': '#16c172', 'stop-opacity': 0 }));
    defs.appendChild(lg);
    svg.appendChild(defs);

    // Grid lines + axis labels
    for (let g = 0; g <= 4; g++) {
        const yy = padT + (plotH / 4) * g;
        svg.appendChild(el('line', { x1: padL, y1: yy, x2: padL + plotW, y2: yy, stroke: 'rgba(255,255,255,.06)', 'stroke-width': 1 }));
        const lt = el('text', { x: padL - 8, y: yy + 4, fill: '#616d79', 'font-size': 10, 'text-anchor': 'end', 'font-family': "'JetBrains Mono', monospace" });
        lt.textContent = Math.round(yMax - (yMax / 4) * g);
        svg.appendChild(lt);
        const rt = el('text', { x: padL + plotW + 10, y: yy + 4, fill: 'rgba(75,159,255,.7)', 'font-size': 10, 'text-anchor': 'start', 'font-family': "'JetBrains Mono', monospace" });
        rt.textContent = Math.round(cMax - (cMax / 4) * g);
        svg.appendChild(rt);
    }

    // X-axis labels
    for (let i = 0; i < n; i++) {
        const xt = el('text', { x: X(i), y: H - 10, fill: '#9aa7b3', 'font-size': 10, 'text-anchor': 'middle' });
        xt.textContent = labels[i];
        svg.appendChild(xt);
    }

    // Cumulative dashed line
    let cd = '';
    for (let c = 0; c < n; c++) cd += (c ? ' L' : 'M') + X(c) + ' ' + YC(cum[c]);
    svg.appendChild(el('path', { d: cd, fill: 'none', stroke: 'rgba(75,159,255,.6)', 'stroke-width': 1.5, 'stroke-dasharray': '5 4' }));

    // Points line + area
    let ld = '';
    for (let p = 0; p < n; p++) ld += (p ? ' L' : 'M') + X(p) + ' ' + Y(pts[p]);
    const ad = 'M' + X(0) + ' ' + (padT + plotH) + ' L' + ld.slice(1) + ' L' + X(n - 1) + ' ' + (padT + plotH) + ' Z';
    svg.appendChild(el('path', { d: ad, fill: 'url(#areaG)', stroke: 'none' }));
    svg.appendChild(el('path', { d: ld, fill: 'none', stroke: '#16c172', 'stroke-width': 2.5, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }));

    // Data points + values
    for (let q = 0; q < n; q++) {
        svg.appendChild(el('circle', { cx: X(q), cy: Y(pts[q]), r: 3.5, fill: '#2bd97a', stroke: '#0a0d11', 'stroke-width': 2 }));
        const vt = el('text', { x: X(q), y: Y(pts[q]) - 10, fill: '#eef2f5', 'font-size': 10, 'text-anchor': 'middle', 'font-family': "'JetBrains Mono', monospace" });
        vt.textContent = pts[q];
        svg.appendChild(vt);
    }
}
