// Fonction pour gérer l'interface en fonction de la source (Achat/Don)
function verifierDon() {
    const source = document.getElementById('select_source').value;
    const inputPrix = document.getElementById('input_prix_achat');
    const inputMarge = document.getElementById('input_marge');
    const selectFournisseur = document.getElementById('select_fournisseur');
    const optFournisseurs = document.getElementById('optgroup_fournisseurs');
    const optDonateurs = document.getElementById('optgroup_donateurs');
    
    if (source === 'Don') {
        // Griser et mettre à 0 les champs prix et marge
        inputPrix.value = 0;
        inputPrix.readOnly = true;
        inputPrix.style.backgroundColor = "#e9ecef";
        
        inputMarge.value = 0;
        inputMarge.readOnly = true;
        inputMarge.style.backgroundColor = "#e9ecef";

        // Gérer la sélection Fournisseur/Donateur
        if (selectFournisseur) {
            if (optFournisseurs) optFournisseurs.disabled = true;
            if (optDonateurs) optDonateurs.disabled = false;
            // Si un fournisseur était sélectionné, on réinitialise
            const selectedOption = selectFournisseur.options[selectFournisseur.selectedIndex];
            if (selectedOption && selectedOption.parentElement.id === 'optgroup_fournisseurs') {
                selectFournisseur.value = '';
            }
        }
    } else { // Cas 'Achat'
        // Rendre les champs modifiables
        inputPrix.readOnly = false;
        inputPrix.style.backgroundColor = "#ffffff";
        inputMarge.readOnly = false;
        inputMarge.style.backgroundColor = "#ffffff";

        // Restaurer les valeurs depuis le produit sélectionné
        const selectProd = document.getElementById('select_produit_entree');
        if(selectProd.selectedIndex > 0) {
            const option = selectProd.options[selectProd.selectedIndex];
            inputPrix.value = option.getAttribute('data-default-prix') || '';
            inputMarge.value = option.getAttribute('data-marge') || '';
        }

        // Gérer la sélection Fournisseur/Donateur
        if (selectFournisseur) {
            if (optFournisseurs) optFournisseurs.disabled = false;
            if (optDonateurs) optDonateurs.disabled = true;
            // Si un donateur était sélectionné, on réinitialise
            const selectedOption = selectFournisseur.options[selectFournisseur.selectedIndex];
            if (selectedOption && selectedOption.parentElement.id === 'optgroup_donateurs') {
                selectFournisseur.value = '';
            }
        }
    }
}

// Gestion des changements de sélection de produit
document.addEventListener('DOMContentLoaded', function() {
    const selectProd = document.getElementById('select_produit_entree');
    const inputPrix = document.getElementById('input_prix_achat');
    const inputMarge = document.getElementById('input_marge');
    const selectSource = document.getElementById('select_source');

    if(selectProd) {
        selectProd.addEventListener('change', function() {
            if (selectSource.value === 'Don') return; // Ne change rien si c'est un don

            const option = selectProd.selectedOptions[0];
            if(option && option.value !== "") {
                inputPrix.value = option.getAttribute('data-default-prix') || '';
                inputMarge.value = option.getAttribute('data-marge') || '';
            }
        });
    }
    // Initialisation au chargement
    verifierDon();
});
