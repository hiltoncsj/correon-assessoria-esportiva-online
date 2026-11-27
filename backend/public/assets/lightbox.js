// Foto em tela cheia. Sem JavaScript, o link abre a própria imagem.
(function () {
	if (!window.HTMLDialogElement) { return; }
	var box = document.createElement('dialog');
	box.className = 'lightbox';
	var img = document.createElement('img');
	var close = document.createElement('button');
	close.type = 'button';
	close.className = 'lightbox__close';
	close.setAttribute('aria-label', 'Fechar');
	var NS = 'http://www.w3.org/2000/svg';
	var svg = document.createElementNS(NS, 'svg');
	var attrs = { viewBox: '0 0 24 24', width: '24', height: '24', 'aria-hidden': 'true', focusable: 'false', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.5', 'stroke-linecap': 'round' };
	Object.keys(attrs).forEach(function (k) { svg.setAttribute(k, attrs[k]); });
	var path = document.createElementNS(NS, 'path');
	path.setAttribute('d', 'M6 6l12 12M18 6L6 18');
	svg.appendChild(path);
	close.appendChild(svg);
	box.appendChild(img);
	box.appendChild(close);
	document.body.appendChild(box);

	// clique em qualquer ponto fecha (Esc já é nativo)
	box.addEventListener('click', function () { box.close(); });
	box.addEventListener('close', function () { document.documentElement.classList.remove('has-lightbox'); });

	document.addEventListener('click', function (e) {
		var link = e.target.closest ? e.target.closest('[data-lightbox]') : null;
		if (!link || e.ctrlKey || e.metaKey || e.shiftKey) { return; }
		e.preventDefault();
		var thumb = link.querySelector('img');
		img.src = link.getAttribute('href');
		img.alt = thumb ? thumb.alt : '';
		box.setAttribute('aria-label', img.alt || 'Foto ampliada');
		document.documentElement.classList.add('has-lightbox');
		box.showModal();
	});
})();
