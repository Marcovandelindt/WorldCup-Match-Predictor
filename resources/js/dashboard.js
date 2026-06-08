document.addEventListener('DOMContentLoaded', () => {
    // Bevestigingsdialoog voor het genereren van een voorspelling
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!confirm(btn.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // Loading state op de "Genereer voorspelling" knop
    document.querySelectorAll('[data-generate]').forEach(btn => {
        btn.addEventListener('click', function () {
            this.setAttribute('aria-busy', 'true');
            this.textContent = 'Berekenen…';
        });
    });

    // Live zoeken op teamnaam
    const searchInput = document.getElementById('team-search');
    if (!searchInput) return;

    const emptyMsg   = document.querySelector('.search-empty');
    const querySpan  = document.getElementById('search-query');
    const sections   = document.querySelectorAll('[data-match-section]');

    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim().toLowerCase();

        let totalVisible = 0;

        sections.forEach(section => {
            const rows = section.querySelectorAll('tbody tr[data-teams]');
            let sectionVisible = 0;

            rows.forEach(row => {
                const teams   = row.dataset.teams;
                const visible = !q || teams.includes(q);
                row.hidden    = !visible;
                if (visible) sectionVisible++;
            });

            section.hidden = sectionVisible === 0 && q !== '';
            totalVisible  += sectionVisible;
        });

        if (emptyMsg && querySpan) {
            querySpan.textContent  = searchInput.value.trim();
            emptyMsg.hidden        = totalVisible > 0 || q === '';
        }
    });
});
