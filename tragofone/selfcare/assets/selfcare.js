(function () {
	'use strict';

	function formatLocalTimes() {
		document.querySelectorAll('time[data-epoch]').forEach(function (element) {
			var epoch = Number(element.getAttribute('data-epoch'));
			if (!Number.isFinite(epoch) || epoch <= 0) return;
			element.textContent = new Intl.DateTimeFormat(undefined, {
				month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
			}).format(new Date(epoch * 1000));
		});
	}

	function dismissNotices() {
		var notices = document.querySelectorAll('[data-auto-dismiss]');
		if (!notices.length) return;
		window.setTimeout(function () {
			notices.forEach(function (notice) {
				notice.classList.add('is-dismissed');
				window.setTimeout(function () { notice.remove(); }, 220);
			});
			if (window.history && window.history.replaceState) {
				var url = new URL(window.location.href);
				url.searchParams.delete('status');
				url.searchParams.delete('message');
				window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
			}
		}, 5000);
	}

	function validateForwardingSelection() {
		var form = document.querySelector('[data-call-handling-form]');
		if (!form) return;
		form.addEventListener('submit', function (event) {
			var invalid = null;
			form.querySelectorAll('[data-forward-rule]').forEach(function (rule) {
				var checkbox = rule.querySelector('input[type="checkbox"]');
				var destination = rule.querySelector('input[type="tel"]');
				if (!invalid && destination && destination.value.trim() !== '' && checkbox && !checkbox.checked) invalid = rule;
			});
			if (!invalid) return;
			event.preventDefault();
			var input = invalid.querySelector('input[type="tel"]');
			input.setCustomValidity('Select ' + invalid.getAttribute('data-forward-label') + ' to enable this forwarding destination.');
			input.reportValidity();
			window.setTimeout(function () { input.setCustomValidity(''); }, 0);
		});
	}

	function markPlayedVoicemailRead() {
		document.querySelectorAll('[data-voicemail-message]').forEach(function (card) {
			var audio = card.querySelector('audio');
			var form = card.querySelector('[data-read-form]');
			if (!audio || !form || card.getAttribute('data-read') === 'true') return;
			audio.addEventListener('playing', function () {
				if (card.getAttribute('data-read') === 'true') return;
				card.setAttribute('data-read', 'true');
				card.classList.remove('unread');
				var badge = card.querySelector('.sc-badge'); if (badge) badge.textContent = 'Read';
				var readInput = form.querySelector('input[name="read"]'); if (readInput) readInput.value = 'false';
				var button = form.querySelector('button'); if (button) button.textContent = 'Mark unread';
			});
		});
	}

	formatLocalTimes();
	dismissNotices();
	validateForwardingSelection();
	markPlayedVoicemailRead();
})();
