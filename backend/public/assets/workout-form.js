// Formulário de treino: adiciona linhas e confirma ações destrutivas. Sem JS o formulário continua funcionando.
(function () {
	var list = document.querySelector('[data-items]');
	var add = document.querySelector('[data-add-row]');
	if (list && add) {
		add.hidden = false;
		add.addEventListener('click', function () {
			var rows = list.querySelectorAll('[data-row]');
			if (rows.length >= 40) { return; }
			var clone = rows[rows.length - 1].cloneNode(true);
			var idx = rows.length;
			clone.querySelectorAll('input').forEach(function (input) {
				input.value = '';
				input.name = input.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
			});
			list.appendChild(clone);
			clone.querySelector('input').focus();
		});
	}
	document.addEventListener('click', function (e) {
		var el = e.target.closest ? e.target.closest('[data-confirm]') : null;
		if (el && !window.confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); }
	});
})();
