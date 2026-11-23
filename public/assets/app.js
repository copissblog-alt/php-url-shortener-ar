// نسخ الرابط، وتأكيد الحذف، وارتفاع أعمدة الإحصائيات (بلا style داخل HTML بسبب سياسة CSP)
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-copy]');
  if (!b) return;
  navigator.clipboard.writeText(b.dataset.copy).then(function () {
    var t = b.textContent; b.textContent = 'نُسخ ✓'; setTimeout(function () { b.textContent = t; }, 1500);
  });
});
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (f.dataset.confirm && !confirm(f.dataset.confirm)) e.preventDefault();
});
document.querySelectorAll('.bar i[data-h]').forEach(function (i) { i.style.height = i.dataset.h + '%'; });
