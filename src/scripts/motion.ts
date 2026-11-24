// Comportamento de movimento compartilhado (ver src/styles/motion.css).

const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// 1. Text roll: divide o texto em caracteres com cópia empilhada.
const ROLL_SELECTOR = [
	'.btn',
	'.hero__cta',
	'.plan__cta',
	'.closing__cta',
	'.site-header__nav li a',
	'.footer__col a',
].join(',');

if (!reduce) {
	document.querySelectorAll<HTMLElement>(ROLL_SELECTOR).forEach((el) => {
		const text = el.textContent?.trim();
		if (!text || el.dataset.roll !== undefined) return;
		el.dataset.roll = '';
		el.textContent = '';

		const sr = document.createElement('span');
		sr.className = 'roll__sr';
		sr.textContent = text;

		const vis = document.createElement('span');
		vis.className = 'roll';
		vis.setAttribute('aria-hidden', 'true');
		Array.from(text).forEach((ch, i) => {
			const c = document.createElement('span');
			c.className = 'roll__char';
			c.dataset.c = ch;
			c.textContent = ch;
			c.style.setProperty('--i', String(i));
			vis.appendChild(c);
		});
		el.append(sr, vis);
	});
}

// 2. Line highlight reveal: marca o título quando entra na tela (uma vez).
const titles = document.querySelectorAll<HTMLElement>('main h2');
if (reduce || !('IntersectionObserver' in window)) {
	titles.forEach((t) => t.classList.add('is-in'));
} else {
	const io = new IntersectionObserver(
		(entries) => {
			entries.forEach((e) => {
				if (e.isIntersecting) {
					e.target.classList.add('is-in');
					io.unobserve(e.target);
				}
			});
		},
		{ rootMargin: '0px 0px -10% 0px' },
	);
	titles.forEach((t) => io.observe(t));
}
