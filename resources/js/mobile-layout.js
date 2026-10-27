// Convertir las tablas administrativas en tarjetas etiquetadas solo en pantallas pequeñas.
document.querySelectorAll('.admin-main table.table').forEach(table => {
    if (!table.tHead || !table.tBodies.length) return;
    const grid = [];
    [...table.tHead.rows].forEach((row, rowIndex) => {
        grid[rowIndex] ||= [];
        let column = 0;
        [...row.cells].forEach(cell => {
            while (grid[rowIndex][column] !== undefined) column++;
            for (let r = 0; r < cell.rowSpan; r++) {
                grid[rowIndex + r] ||= [];
                for (let c = 0; c < cell.colSpan; c++) grid[rowIndex + r][column + c] = cell.textContent.trim();
            }
            column += cell.colSpan;
            cell.setAttribute('role', 'columnheader');
        });
    });
    const labels = grid.at(-1);
    table.classList.add('mobile-record-table');
    table.setAttribute('role', 'table');
    table.tHead.setAttribute('role', 'rowgroup');
    [...table.tHead.rows].forEach(row => row.setAttribute('role', 'row'));
    [...table.tBodies].forEach(body => {
        body.setAttribute('role', 'rowgroup');
        [...body.rows].forEach(row => {
            row.setAttribute('role', 'row');
            if (row.cells.length === 1 && row.cells[0].colSpan > 1) {
                row.classList.add('mobile-empty-row');
                return;
            }
            [...row.cells].forEach((cell, index) => {
                cell.setAttribute('role', 'cell');
                cell.dataset.label = labels[index] || 'Acciones';
                const content = document.createElement('div');
                content.className = 'mobile-cell-value';
                while (cell.firstChild) content.append(cell.firstChild);
                cell.append(content);
            });
        });
    });
    // La tabla vuelve a su presentación habitual al regresar a escritorio.
});
