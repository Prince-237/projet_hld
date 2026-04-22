const searchInput = document.getElementById('searchInput');
const sourceFilter = document.getElementById('sourceFilter');
const destinationFilter = document.getElementById('destinationFilter');
const statusFilter = document.getElementById('statusFilter');
const agentFilter = document.getElementById('agentFilter');
const dateStart = document.getElementById('dateFilterStart');
const dateEnd = document.getElementById('dateFilterEnd');
const tableBody = document.getElementById('tableBody');

function loadData() {
    if (!tableBody) {
        return;
    }

    const params = new URLSearchParams({
        search: searchInput ? searchInput.value.trim() : '',
        source: sourceFilter ? sourceFilter.value : '',
        destination: destinationFilter ? destinationFilter.value : '',
        status: statusFilter ? statusFilter.value : '',
        agent: agentFilter ? agentFilter.value : '',
        dateStart: dateStart ? dateStart.value : '',
        dateEnd: dateEnd ? dateEnd.value : ''
    });

    fetch('fetch_liste_transferts.php?' + params.toString())
        .then((res) => res.text())
        .then((data) => {
            tableBody.innerHTML = data;
        })
        .catch((err) => {
            console.error('Erreur fetch_liste_transferts:', err);
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Erreur de chargement des données.</td></tr>';
        });
}

[sourceFilter, destinationFilter, statusFilter, agentFilter, searchInput, dateStart, dateEnd].forEach((el) => {
    if (!el) {
        return;
    }
    el.addEventListener('input', loadData);
    el.addEventListener('change', loadData);
});

window.addEventListener('DOMContentLoaded', loadData);
