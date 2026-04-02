const type = document.getElementById('typeFilter');
const searchInput = document.getElementById('searchInput');
const fournisseur = document.getElementById('fournisseurFilter');
const dateStart = document.getElementById('dateFilterStart');
const dateEnd = document.getElementById('dateFilterEnd');
const agent = document.getElementById('agentFilter');
const tableBody = document.getElementById('tableBody');

function loadData() {
    if (!tableBody) return;

    const params = new URLSearchParams({
        type: type ? type.value : 'Medicament',
        search: searchInput ? searchInput.value : '',
        fournisseur: fournisseur ? fournisseur.value : '',
        dateStart: dateStart ? dateStart.value : '',
        dateEnd: dateEnd ? dateEnd.value : '',
        agent: agent ? agent.value : ''
    });

    fetch('fetch_entrees_stock.php?' + params.toString())
        .then(res => res.text())
        .then(data => {
            tableBody.innerHTML = data;
        })
        .catch(err => {
            console.error('Erreur fetch_entrees_stock:', err);
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erreur de chargement des données.</td></tr>';
        });
}

[type, searchInput, fournisseur, dateStart, dateEnd, agent].forEach(el => {
    if (!el) return;
    el.addEventListener('input', loadData);
    el.addEventListener('change', loadData);
});

window.addEventListener('DOMContentLoaded', loadData);

