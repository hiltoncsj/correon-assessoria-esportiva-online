// Formulários de treino/modelo: adiciona linhas e sessões, confirma ações destrutivas. Sem JS tudo continua funcionando.
(function () {
	function reindexRows(block, sIdx) {
		var rows = block.querySelectorAll('[data-row]');
		rows.forEach(function (row, i) {
			row.querySelectorAll('input, select').forEach(function (el) {
				if (sIdx !== null) { el.name = el.name.replace(/sessions\[\d+\]/, 'sessions[' + sIdx + ']'); }
				el.name = el.name.replace(/items\[\d+\]/, 'items[' + i + ']');
			});
		});
	}
	function clearFields(root) {
		root.querySelectorAll('input[type=text], input:not([type]), textarea').forEach(function (el) { el.value = ''; });
		root.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
	}
	document.querySelectorAll('[data-add-row], [data-add-slot]').forEach(function (b) { b.hidden = false; });
	var addSession = document.querySelector('[data-add-session]');
	if (addSession) { addSession.hidden = false; }

	document.addEventListener('change', function (e) {
		var el = e.target;
		if (el.matches && el.matches('input[data-athlete]') && el.checked) {
			document.querySelectorAll('input[data-athlete="' + el.getAttribute('data-athlete') + '"]').forEach(function (o) { if (o !== el) { o.checked = false; } });
		}
	});
	document.addEventListener('click', function (e) {
		var t = e.target.closest ? e.target.closest('[data-add-row],[data-add-session],[data-add-slot],[data-confirm]') : null;
		if (!t) { return; }
		if (t.hasAttribute('data-confirm')) {
			if (!window.confirm(t.getAttribute('data-confirm'))) { e.preventDefault(); }
			return;
		}
		if (t.hasAttribute('data-add-slot')) {
			var day = t.closest('[data-day]');
			var slots = day.querySelectorAll('select[data-slot]');
			if (slots.length >= 10) { return; }
			var slot = slots[slots.length - 1].cloneNode(true);
			slot.value = '';
			slot.querySelectorAll('option').forEach(function (o) { o.removeAttribute('selected'); });
			slot.selectedIndex = 0;
			day.insertBefore(slot, t);
			slot.focus();
			return;
		}
		if (t.hasAttribute('data-add-row')) {
			var block = t.closest('[data-block]');
			var list = block.querySelector('[data-items]');
			var rows = list.querySelectorAll('[data-row]');
			if (rows.length >= 40) { return; }
			var clone = rows[rows.length - 1].cloneNode(true);
			clearFields(clone);
			list.appendChild(clone);
			var sess = t.closest('[data-session]');
			var sIdx = null;
			if (sess) { var m = clone.querySelector('input').name.match(/sessions\[(\d+)\]/); sIdx = m ? m[1] : null; }
			reindexRows(block, sIdx);
			clone.querySelector('input').focus();
			return;
		}
		if (t.hasAttribute('data-add-session')) {
			var all = document.querySelectorAll('[data-session]');
			if (all.length >= 100) { return; }
			var copy = all[all.length - 1].cloneNode(true);
			clearFields(copy);
			var idx = all.length;
			all[all.length - 1].parentNode.insertBefore(copy, t.parentNode);
			copy.querySelectorAll('input, select, textarea').forEach(function (el) {
				el.name = el.name.replace(/sessions\[\d+\]/, 'sessions[' + idx + ']');
			});
			var title = copy.querySelector('[data-session-title]');
			if (title) { title.textContent = 'Sessão ' + (idx + 1); }
			copy.querySelector('input').focus();
		}
	});
})();
