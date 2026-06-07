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
});
