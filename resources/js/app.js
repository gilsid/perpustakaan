// Peningkatan progresif: semua halaman tetap berfungsi tanpa JavaScript.
(function () {
  'use strict';

  // Tombol menu (header situs & sidebar admin)
  document.querySelectorAll('.nav-toggle').forEach(function (btn) {
    var target = document.getElementById(btn.getAttribute('aria-controls'));
    if (!target) return;
    btn.addEventListener('click', function () {
      var open = target.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && target.classList.contains('is-open')) {
        target.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        btn.focus();
      }
    });
  });

  // Menu akun (<details>): tutup saat klik di luar atau tekan Escape
  var menus = document.querySelectorAll('details.menu');
  document.addEventListener('click', function (e) {
    menus.forEach(function (m) { if (m.open && !m.contains(e.target)) m.open = false; });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') menus.forEach(function (m) { if (m.open) { m.open = false; m.querySelector('summary').focus(); } });
  });

  // Pesan flash bisa ditutup
  document.querySelectorAll('[data-dismiss]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('.alert').remove(); });
  });

  // Panel filter katalog: terbuka di layar lebar, tertutup di ponsel
  var filters = document.querySelector('details[data-open-wide]');
  if (filters && window.matchMedia) {
    var mq = window.matchMedia('(min-width: 960px)');
    var sync = function () { filters.open = mq.matches; };
    sync();
    (mq.addEventListener ? mq.addEventListener('change', sync) : mq.addListener(sync));
  }

  // Form katalog: ganti urutan atau kategori langsung memuat ulang hasil
  document.querySelectorAll('form[data-autosubmit]').forEach(function (form) {
    form.querySelectorAll('select[name="urut"], select[name="bahasa"], input[name="kategori"], input[name="digital"]').forEach(function (el) {
      el.addEventListener('change', function () { form.submit(); });
    });
  });

  // Tampilkan/sembunyikan kata sandi
  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    var wrap = document.createElement('div');
    wrap.className = 'pw';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw__toggle';
    btn.setAttribute('aria-label', 'Tampilkan kata sandi');
    btn.setAttribute('aria-pressed', 'false');
    btn.innerHTML = '<svg class="i" aria-hidden="true"><use href="#i-eye"/></svg>';
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
      btn.querySelector('use').setAttribute('href', show ? '#i-eye-off' : '#i-eye');
    });
    wrap.appendChild(btn);
  });

  // Dialog konfirmasi untuk form bertanda data-confirm
  var dialog = document.getElementById('confirm-dialog');
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (form.dataset.confirmed === '1') return;
      e.preventDefault();
      var message = form.dataset.confirm;
      var label = form.dataset.confirmLabel || 'Lanjutkan';
      if (!dialog || typeof dialog.showModal !== 'function') {
        if (window.confirm(message)) { form.dataset.confirmed = '1'; form.submit(); }
        return;
      }
      dialog.querySelector('#confirm-message').textContent = message;
      dialog.querySelector('#confirm-ok').textContent = label;
      dialog.returnValue = '';
      dialog.addEventListener('close', function onClose() {
        dialog.removeEventListener('close', onClose);
        if (dialog.returnValue === 'ok') { form.dataset.confirmed = '1'; form.submit(); }
      });
      dialog.showModal();
    });
  });

  // Cegah kirim ganda: nonaktifkan tombol submit setelah diklik
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      form.querySelectorAll('button[type="submit"]').forEach(function (b) {
        setTimeout(function () { b.disabled = true; }, 0);
      });
    });
  });
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) document.querySelectorAll('button[type="submit"]:disabled').forEach(function (b) { b.disabled = false; });
  });

  // Pratinjau sampul di form buku admin
  var coverInput = document.getElementById('f-cover');
  var preview = document.querySelector('[data-cover-preview]');
  if (coverInput && preview) {
    coverInput.addEventListener('change', function () {
      var file = coverInput.files && coverInput.files[0];
      if (!file || !/^image\//.test(file.type)) return;
      var img = document.createElement('img');
      img.alt = 'Pratinjau sampul baru';
      img.src = URL.createObjectURL(file);
      var wrap = document.createElement('span');
      wrap.className = 'cover cover--img';
      wrap.appendChild(img);
      preview.replaceChildren(wrap);
    });
  }
})();
