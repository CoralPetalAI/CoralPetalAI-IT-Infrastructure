// Live "passwords match" check for any form with data-pw1 / data-pw2 fields (admin + Academy).
(function () {
  function wire(form) {
    var a = form.querySelector('[data-pw1]'), b = form.querySelector('[data-pw2]');
    if (!a || !b) return;
    var msg = form.querySelector('.pwmsg');
    if (!msg) { msg = document.createElement('p'); msg.className = 'pwmsg'; msg.setAttribute('aria-live', 'polite'); b.parentNode.insertAdjacentElement('afterend', msg); }
    function check() {
      a.classList.remove('pw-ok', 'pw-bad'); b.classList.remove('pw-ok', 'pw-bad'); msg.className = 'pwmsg'; msg.textContent = '';
      if (!b.value) return;
      if (a.value === b.value && a.value.length >= (+a.getAttribute('minlength') || 1)) {
        a.classList.add('pw-ok'); b.classList.add('pw-ok'); msg.className = 'pwmsg ok'; msg.textContent = '✓ Passwords match';
      } else if (a.value === b.value) {
        b.classList.add('pw-bad'); msg.className = 'pwmsg bad'; msg.textContent = 'Passwords match, but use at least ' + a.getAttribute('minlength') + ' characters';
      } else {
        b.classList.add('pw-bad'); msg.className = 'pwmsg bad'; msg.textContent = '✕ Passwords do not match';
      }
    }
    a.addEventListener('input', check); b.addEventListener('input', check);
    form.addEventListener('submit', function (e) { if (a.value !== b.value) { e.preventDefault(); check(); b.focus(); } });
  }
  document.querySelectorAll('form').forEach(wire);
})();
// Close the account menu when clicking elsewhere.
document.addEventListener('click', function (e) { document.querySelectorAll('details.acct[open]').forEach(function (d) { if (!d.contains(e.target)) d.open = false; }); });
