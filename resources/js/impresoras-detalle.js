document.querySelectorAll('.impresoras-tabla').forEach((table) => {
    const rows = [...table.querySelectorAll('[data-impresora-row]')];

    const seleccionar = (row) => {
        const detalle = document.getElementById(row.getAttribute('aria-controls'));
        const estabaAbierto = row.getAttribute('aria-expanded') === 'true';

        rows.forEach((otraFila) => {
            otraFila.setAttribute('aria-expanded', 'false');
            otraFila.classList.remove('is-selected');
            document.getElementById(otraFila.getAttribute('aria-controls'))?.classList.add('d-none');
        });

        if (!estabaAbierto && detalle) {
            row.setAttribute('aria-expanded', 'true');
            row.classList.add('is-selected');
            detalle.classList.remove('d-none');
        }
    };

    table.addEventListener('click', (event) => {
        if (event.target.closest('a, button, form, input, select, label')) return;

        const row = event.target.closest('[data-impresora-row]');
        if (row) seleccionar(row);
    });

    table.addEventListener('keydown', (event) => {
        const row = event.target.closest('[data-impresora-row]');
        if (!row || !['Enter', ' '].includes(event.key)) return;

        event.preventDefault();
        seleccionar(row);
    });
});
