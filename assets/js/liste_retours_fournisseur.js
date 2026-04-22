const dateFilterStart = document.getElementById('dateFilterStart');
const dateFilterEnd = document.getElementById('dateFilterEnd');
const fournisseurFilter = document.getElementById('fournisseurFilter');
const agentFilter = document.getElementById('agentFilter');
const tableBody = document.getElementById('tableBody');

function loadData() {
    if (!tableBody) {
        return;
    }

    const params = new URLSearchParams({
        dateStart: dateFilterStart ? dateFilterStart.value : '',
        dateEnd: dateFilterEnd ? dateFilterEnd.value : '',
        fournisseur: fournisseurFilter ? fournisseurFilter.value : '',
        agent: agentFilter ? agentFilter.value : ''
    });

    fetch('fetch_liste_retours_fournisseur.php?' + params.toString())
        .then((res) => res.text())
        .then((data) => {
            tableBody.innerHTML = data;
        })
        .catch((err) => {
            console.error('Erreur fetch_liste_retours_fournisseur:', err);
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erreur de chargement des données.</td></tr>';
        });
}

[dateFilterStart, dateFilterEnd, fournisseurFilter, agentFilter].forEach((el) => {
    if (!el) {
        return;
    }
    el.addEventListener('input', loadData);
    el.addEventListener('change', loadData);
});

window.addEventListener('DOMContentLoaded', loadData);
