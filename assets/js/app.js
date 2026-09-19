document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.toast').forEach((el) => {
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transition = 'opacity .4s';
      setTimeout(() => el.remove(), 400);
    }, 4200);
  });

  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      if (!confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  const busca = document.querySelector('[data-filter-table]');
  if (busca) {
    busca.addEventListener('input', () => {
      const q = busca.value.toLowerCase();
      const table = document.querySelector(busca.getAttribute('data-filter-table'));
      if (!table) return;
      table.querySelectorAll('tbody tr').forEach((tr) => {
        tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }
});
