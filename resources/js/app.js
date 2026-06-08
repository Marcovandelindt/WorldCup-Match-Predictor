import 'flag-icons/css/flag-icons.min.css';
import './dashboard.js';
import './statistics.js';

// Import notice — dismiss for rest of day via localStorage
document.addEventListener('DOMContentLoaded', () => {
    const notice = document.getElementById('importNotice');
    if (!notice) return;

    const key = 'importNoticeHidden_' + new Date().toISOString().slice(0, 10);

    if (localStorage.getItem(key)) {
        notice.remove();
        return;
    }

    document.getElementById('importNoticeClose').addEventListener('click', () => {
        localStorage.setItem(key, '1');
        notice.classList.add('is-hiding');
        setTimeout(() => notice.remove(), 300);
    });
});

// Animated bar fills (prediction page + statistics comparison)
document.addEventListener('DOMContentLoaded', () => {
    requestAnimationFrame(() => {
        document.querySelectorAll('.bar-fill[data-w], .weight-track .fill[data-w], .cbar[data-w]').forEach(el => {
            el.style.width = el.dataset.w + '%';
        });
    });
});

// Clickable table rows
document.addEventListener('click', e => {
    if (e.target.closest('a,button')) return;
    const row = e.target.closest('[data-href]');
    if (row) window.location.href = row.dataset.href;
});

