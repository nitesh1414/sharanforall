/* Sharan Foundation — Shared JS */
document.addEventListener('DOMContentLoaded', () => {
  // Year in footer
  const y = document.getElementById('year');
  if (y) y.textContent = new Date().getFullYear();

  // Mobile menu toggle
  const toggle = document.querySelector('.menu-toggle');
  const navList = document.getElementById('navlist');
  if (toggle && navList) {
    toggle.addEventListener('click', () => navList.classList.toggle('open'));
    navList.querySelectorAll('a').forEach(a => a.addEventListener('click', () => navList.classList.remove('open')));
  }

  // Gallery lightbox
  document.querySelectorAll('.gallery-item').forEach(item => {
    item.addEventListener('click', () => {
      const src = item.dataset.src || item.querySelector('img')?.src;
      const cap = item.dataset.caption || '';
      if (!src) return;
      const lb = document.createElement('div');
      lb.className = 'lightbox';
      lb.innerHTML = `
        <span class="lb-close">&times;</span>
        <img src="${src}" alt="${cap}">
        <div class="lb-caption">${cap}</div>`;
      document.body.appendChild(lb);
      document.body.style.overflow = 'hidden';
      lb.addEventListener('click', e => {
        if (e.target.classList.contains('lightbox') || e.target.classList.contains('lb-close')) {
          lb.remove();
          document.body.style.overflow = '';
        }
      });
    });
  });

  // Gallery filter
  const filterBtns = document.querySelectorAll('.filter-btn');
  if (filterBtns.length) {
    filterBtns.forEach(b => b.addEventListener('click', () => {
      filterBtns.forEach(x => x.classList.remove('active'));
      b.classList.add('active');
      const cat = b.dataset.filter;
      document.querySelectorAll('.gallery-item').forEach(item => {
        item.style.display = (cat === 'all' || item.dataset.category === cat) ? '' : 'none';
      });
    }));
  }

  // Generic form handler
  document.querySelectorAll('form[data-acts-form]').forEach(f => {
    f.addEventListener('submit', e => {
      e.preventDefault();
      alert('Thank you! Your message has been received. We will be in touch shortly. God bless!');
      f.reset();
    });
  });
});
