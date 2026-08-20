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

	function enableIosVoicemailSharing() {
		var appleMobile = /iPhone|iPad|iPod/i.test(navigator.userAgent) ||
			(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
		if (!appleMobile || typeof window.File !== 'function' || typeof navigator.share !== 'function') return;

		var cachedFiles = new WeakMap();
		var status = document.querySelector('[data-download-status]');
		function showStatus(message, error) {
			if (!status) return;
			status.textContent = message;
			status.hidden = false;
			status.classList.toggle('error', !!error);
			status.classList.toggle('success', !error);
			status.scrollIntoView({block: 'nearest'});
		}
		async function share(link, file) {
			if (typeof navigator.canShare === 'function' && !navigator.canShare({files: [file]})) throw new Error('This iOS app does not support sharing audio files.');
			await navigator.share({files: [file], title: 'Voicemail message'});
			link.textContent = 'Share / Save';
			showStatus('Use Save to Files in the iOS share sheet to keep this voicemail.', false);
		}

		document.querySelectorAll('[data-voicemail-download]').forEach(function (link) {
			link.textContent = 'Share / Save';
			link.addEventListener('click', async function (event) {
				event.preventDefault();
				if (cachedFiles.has(link)) {
					try { await share(link, cachedFiles.get(link)); }
					catch (error) { if (error.name !== 'AbortError') showStatus(error.message || 'Unable to open the iOS share sheet.', true); }
					return;
				}

				var original = link.textContent;
				link.textContent = 'Preparing…';
				link.setAttribute('aria-disabled', 'true');
				try {
					var response = await fetch(link.href, {credentials: 'same-origin', cache: 'no-store'});
					if (!response.ok) throw new Error('The voicemail link expired. Refresh this page and try again.');
					var blob = await response.blob();
					if (!blob.size) throw new Error('The voicemail file is empty.');
					if (blob.type && !/^audio\//i.test(blob.type)) throw new Error('The voicemail response was not an audio file. Refresh this page and try again.');
					var file = new File([blob], link.getAttribute('data-filename') || 'voicemail-message.wav', {type: blob.type || 'audio/wav'});
					cachedFiles.set(link, file);
					link.removeAttribute('aria-disabled');
					await share(link, file);
				} catch (error) {
					link.removeAttribute('aria-disabled');
					link.textContent = cachedFiles.has(link) ? 'Tap again to Share / Save' : original;
					if (error.name === 'AbortError') return;
					if (error.name === 'NotAllowedError' && cachedFiles.has(link)) {
						showStatus('The voicemail is ready. Tap Share / Save again to open the iOS share sheet.', false);
						return;
					}
					showStatus(error.message || 'Unable to prepare this voicemail for sharing.', true);
				}
			});
		});
	}

	formatLocalTimes();
	dismissNotices();
	validateForwardingSelection();
	markPlayedVoicemailRead();
	enableIosVoicemailSharing();
})();
