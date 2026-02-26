// assets/js/sorties_validation.js
document.addEventListener('DOMContentLoaded', function(){
  const form = document.querySelector('#sortieForm'); // adapte l'id si besoin
  if(!form) return;
  const src = form.querySelector('select[name="source_id"]');
  const dst = form.querySelector('select[name="destination_id"]');
  const submit = form.querySelector('button[type="submit"]');

  function validate(){
    if(!src || !dst) return;
    const same = src.value !== '' && src.value === dst.value;
    if(same){
      submit.disabled = true;
      let err = form.querySelector('.same-error');
      if(!err){
        err = document.createElement('div');
        err.className = 'same-error';
        err.style.color = 'red';
        err.textContent = 'La source et la destination ne peuvent pas être identiques.';
        dst.parentNode.insertBefore(err, dst.nextSibling);
      }
    } else {
      submit.disabled = false;
      const err = form.querySelector('.same-error');
      if(err) err.remove();
    }
  }
  src && src.addEventListener('change', validate);
  dst && dst.addEventListener('change', validate);
  validate();
});
