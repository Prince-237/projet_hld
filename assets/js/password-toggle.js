document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.toggle-password').forEach(function (btn) {
    btn.addEventListener('click', function () {
      // find the input in the same wrapper
      var input = btn.parentElement.querySelector('input');
      if (!input) return;

      // ensure an icon exists
      var icon = btn.querySelector('i');
      if (!icon) {
        icon = document.createElement('i');
        icon.classList.add('bi', 'bi-eye');
        btn.textContent = '';
        btn.appendChild(icon);
      } else {
        btn.childNodes.forEach(function(node) {
          if (node !== icon) btn.removeChild(node);
        });
      }

      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
        btn.setAttribute('aria-pressed', 'true');
      } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
        btn.setAttribute('aria-pressed', 'false');
      }
    });
  });
});

