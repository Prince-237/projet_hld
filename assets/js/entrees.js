


document.addEventListener('DOMContentLoaded', function() {

    // --- MODAL: EDIT LOT ---
    const modalEdit = document.getElementById('modalEditLot');
    if (modalEdit) {
        modalEdit.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_id_lot').value = button.getAttribute('data-id');
            document.getElementById('edit_num_lot').value = button.getAttribute('data-num');
            document.getElementById('edit_exp').value = button.getAttribute('data-exp');
            document.getElementById('edit_qte').value = button.getAttribute('data-qte');

            const prix = button.getAttribute('data-prix');
            const inputPrix = document.getElementById('edit_prix');
            inputPrix.value = prix;

            // Si le prix est 0 (Don), on empêche la modification du prix pour rester cohérent
            if (parseFloat(prix) === 0) {
                inputPrix.readOnly = true;
                inputPrix.classList.add('bg-light');
            } else {
                inputPrix.readOnly = false;
                inputPrix.classList.remove('bg-light');
            }
        });
    }

    // --- MODAL: RECEPTION ---
    const modalRecv = document.getElementById('modalReception');
    if (modalRecv) {
        modalRecv.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('recv_id_cmd').value = btn.getAttribute('data-id-cmd');
            document.getElementById('recv_id_det').value = btn.getAttribute('data-id-det');
            document.getElementById('recv_id_prod').value = btn.getAttribute('data-id-prod');
            document.getElementById('recv_nom_prod').textContent = btn.getAttribute('data-nom');
            document.getElementById('recv_qte').value = btn.getAttribute('data-qte');

            const prix = btn.getAttribute('data-prix');
            const marge = btn.getAttribute('data-marge');
            const source = btn.getAttribute('data-source');

            const inputPrix = document.getElementById('recv_prix');
            const inputMarge = document.getElementById('recv_marge');

            inputPrix.value = prix;
            inputMarge.value = marge;

            if (source === 'Don') {
                inputPrix.readOnly = true;
                inputPrix.value = 0;
                inputMarge.readOnly = true;
                inputMarge.value = 0;
            } else {
                inputPrix.readOnly = false;
                inputMarge.readOnly = false;
            }
        });
    }

    // --- MODAL: EDIT COMMANDE (En attente) ---
    const modalEditCmd = document.getElementById('modalEditCommande');
    if (modalEditCmd) {
        modalEditCmd.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('cmd_edit_id').value = btn.getAttribute('data-id');
            document.getElementById('cmd_edit_nom').value = btn.getAttribute('data-nom');
            document.getElementById('cmd_edit_qte').value = btn.getAttribute('data-qte');
        });
    }

    // --- FORM: NOUVELLE COMMANDE ---
    const mainForm = document.querySelector('form button[name="btn_creer_commande"]');
    if (!mainForm) return; // Exit if not the main form

    const selectSource = document.getElementById('select_source');
    const inputPrix = document.getElementById('input_prix_achat');
    const inputMarge = document.getElementById('input_marge');
    const selectPartenaire = document.getElementById('select_fournisseur');
    const selectProduit = document.getElementById('select_produit_entree');

    // Function to update partner dropdown based on source
    function updatePartnerList() {
        if (!selectSource || !selectPartenaire) return;
        const isDon = selectSource.value === 'Don';
        
        const optGroupFours = selectPartenaire.querySelector('#optgroup_fournisseurs');
        const optGroupDons = selectPartenaire.querySelector('#optgroup_donateurs');

        if (optGroupFours) optGroupFours.style.display = isDon ? 'none' : 'block';
        if (optGroupDons) optGroupDons.style.display = isDon ? 'block' : 'none';

        const selectedOption = selectPartenaire.options[selectPartenaire.selectedIndex];
        if (selectedOption && selectedOption.value !== "" && selectedOption.parentElement.style.display === 'none') {
            selectPartenaire.value = '';
        }
    }

    // Function to update price/margin fields based on source and product selection
    function updatePriceAndMargin() {
        if (!selectSource || !inputPrix || !inputMarge) return;
        const isDon = selectSource.value === 'Don';

        if (isDon) {
            inputPrix.value = 0;
            inputPrix.setAttribute('readonly', true);
            inputPrix.classList.add('bg-light');

            inputMarge.value = 0;
            inputMarge.setAttribute('readonly', true);
            inputMarge.classList.add('bg-light');
        } else {
            inputPrix.removeAttribute('readonly');
            inputPrix.classList.remove('bg-light');

            inputMarge.removeAttribute('readonly');
            inputMarge.classList.remove('bg-light');

            if (selectProduit && selectProduit.value) {
                const selectedOption = selectProduit.options[selectProduit.selectedIndex];
                inputPrix.value = selectedOption.getAttribute('data-default-prix') || '';
                inputMarge.value = selectedOption.getAttribute('data-marge') || '';
            } else {
                // Clear if no product is selected
                inputPrix.value = '';
                inputMarge.value = '';
            }
        }
    }

    if (selectSource) {
        selectSource.addEventListener('change', () => {
            updatePartnerList();
            updatePriceAndMargin();
        });
    }

    if (selectProduit) {
        selectProduit.addEventListener('change', updatePriceAndMargin);
    }

    // Initial state on page load
    updatePartnerList();
    updatePriceAndMargin();
});

