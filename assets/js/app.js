// app.js — pemanis kecil bersama untuk semua halaman dashboard.
document.addEventListener('DOMContentLoaded', function () {
  // Alert flash (hasil redirect_with_flash) otomatis hilang setelah beberapa detik.
  document.querySelectorAll('.alert-dismissible').forEach(function (el) {
    setTimeout(function () {
      if (window.bootstrap && bootstrap.Alert) {
        bootstrap.Alert.getOrCreateInstance(el).close();
      } else {
        el.remove();
      }
    }, 5000);
  });
});
