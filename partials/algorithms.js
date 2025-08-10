// searching and filtering algorithms
function filterTable(searchInputId, tableId) {
    // Build index only once per table
    if (!filterTable._indexes) {
        filterTable._indexes = {};
    }
    if (!filterTable._indexes[tableId]) {
        const rows = document.querySelectorAll(`#${tableId} tbody tr`);
        filterTable._indexes[tableId] = Array.from(rows).map(row => ({
            element: row,
            text: row.textContent
                .toLowerCase()
                .normalize("NFD")
                .replace(/\p{Diacritic}/gu, "")
        }));
    }

    const filter = document.getElementById(searchInputId).value
        .toLowerCase()
        .normalize("NFD")
        .replace(/\p{Diacritic}/gu, "");

    filterTable._indexes[tableId].forEach(item => {
        item.element.style.display =
            (filter === "" || item.text.includes(filter)) ? "" : "none";
    });
}



