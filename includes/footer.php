    </main>
    <footer class="mt-5 py-4 bg-white border-top text-center text-muted">
    <div class="container">
        Système de Gestion de la Pharmacie | Hôpital Laquintinie de Douala</p>
    </div>
</footer>
    </div>
</div>

<!-- Modal Profil Utilisateur -->
<div class="modal fade" id="userProfileModal" tabindex="-1" aria-labelledby="userProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userProfileModalLabel">Profil Utilisateur</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong>Nom Complet :</strong> <?= isset($_SESSION['nom']) ? htmlspecialchars($_SESSION['nom']) : 'N/A' ?></p>
        <p><strong>Nom d'utilisateur :</strong> <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'N/A' ?></p>
        <p><strong>Email :</strong> <?= isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'N/A' ?></p>
        <p><strong>Rôle :</strong> <span class="badge bg-primary"><?= isset($_SESSION['role']) ? htmlspecialchars(ucfirst($_SESSION['role'])) : 'N/A' ?></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
