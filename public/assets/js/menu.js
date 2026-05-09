/* Menu page: tab switching + category nav highlighting */

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.rest-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.target).classList.add('active');
  });
});

// Highlight active category on scroll
const sections = document.querySelectorAll('.cat-section');
const catLinks  = document.querySelectorAll('.cat-link');

const observer = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      catLinks.forEach(l => l.classList.remove('active'));
      const target = document.querySelector(`.cat-link[href="#${e.target.id}"]`);
      if (target) target.classList.add('active');
    }
  });
}, { threshold: 0.3 });

sections.forEach(s => observer.observe(s));
