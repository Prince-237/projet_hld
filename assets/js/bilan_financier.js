// assets/js/bilan_financier.js
document.addEventListener('DOMContentLoaded', function(){
  const form = document.querySelector('.filter-form');
  if(!form) return;
  form.querySelectorAll('select').forEach(s => s.addEventListener('change', ()=> form.submit()));
});
