/* NVGSO theme interactions: menu, search, accessibility, cookie consent, gallery. */
(function () {
	'use strict';

	var $ = function (sel, root) { return (root || document).querySelector(sel); };
	var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
	var COOKIE = 'nvgso_consent';
	var A11Y_KEY = 'nvgso_a11y_visually_impaired';
	var MAX_AGE = 60 * 60 * 24 * 180;
	var lastFocus = null;

	/* ---------- helpers ---------- */
	function toast(message, type) {
		var wrap = $('.toast-wrap');
		if (!wrap) {
			wrap = document.createElement('div');
			wrap.className = 'toast-wrap';
			document.body.appendChild(wrap);
		}
		var node = document.createElement('div');
		node.className = 'toast' + (type === 'error' ? ' toast--error' : '');
		node.textContent = message;
		wrap.appendChild(node);
		setTimeout(function () { node.classList.add('is-visible'); }, 20);
		setTimeout(function () {
			node.classList.remove('is-visible');
			setTimeout(function () { node.remove(); }, 300);
		}, 4000);
	}

	function trap(container, onEscape) {
		lastFocus = document.activeElement;
		var handler = function (event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				if (onEscape) onEscape();
			}
			if (event.key !== 'Tab') return;
			var focusable = $$('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])', container)
				.filter(function (el) { return el.offsetParent !== null; });
			if (!focusable.length) return;
			var first = focusable[0];
			var last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
			else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
		};
		container.addEventListener('keydown', handler);
		container.__trap = handler;
	}

	function untrap(container) {
		if (container.__trap) container.removeEventListener('keydown', container.__trap);
		container.__trap = null;
		if (lastFocus && lastFocus.focus) lastFocus.focus();
	}

	function lockScroll() { document.body.classList.add('no-scroll'); }
	function unlockScroll() { document.body.classList.remove('no-scroll'); }

	/* ---------- mobile menu ---------- */
	var mobile = $('#mobile-nav');
	function openMobile() {
		if (!mobile) return;
		mobile.classList.add('is-open');
		lockScroll();
		trap(mobile, closeMobile);
		var first = $('[data-mobile-close]', mobile);
		if (first) first.focus();
	}
	function closeMobile() {
		if (!mobile) return;
		mobile.classList.remove('is-open');
		unlockScroll();
		untrap(mobile);
	}

	/* ---------- search ---------- */
	var search = $('#search-modal');
	function openSearch() {
		if (!search) return;
		search.classList.add('is-open');
		lockScroll();
		var input = $('#search-modal-input');
		if (input) setTimeout(function () { input.focus(); }, 40);
		trap(search, closeSearch);
	}
	function closeSearch() {
		if (!search) return;
		search.classList.remove('is-open');
		unlockScroll();
		untrap(search);
	}

	/* ---------- версия для слабовидящих ---------- */
	function a11yOn() {
		try { return localStorage.getItem(A11Y_KEY) === '1'; } catch (e) { return false; }
	}
	function applyA11y(on) {
		var root = document.documentElement;
		root.style.setProperty('--a11y-scale', on ? '1.25' : '1');
		if (on) {
			root.setAttribute('data-a11y-large', '1');
			root.setAttribute('data-a11y-contrast', 'hc');
			root.setAttribute('data-a11y-links', '1');
			root.setAttribute('data-a11y-motion', 'off');
		} else {
			root.removeAttribute('data-a11y-large');
			root.removeAttribute('data-a11y-contrast');
			root.removeAttribute('data-a11y-links');
			root.removeAttribute('data-a11y-motion');
		}
		$$('[data-a11y-toggle]').forEach(function (button) {
			button.setAttribute('aria-pressed', String(on));
			button.setAttribute('aria-label', on ? 'Выключить версию для слабовидящих' : 'Включить версию для слабовидящих');
		});
		$$('[data-a11y-label]').forEach(function (label) {
			label.textContent = on ? 'Обычная версия' : 'Для слабовидящих';
		});
	}
	function toggleA11y() {
		var on = !a11yOn();
		try { localStorage.setItem(A11Y_KEY, on ? '1' : '0'); } catch (e) {}
		applyA11y(on);
		toast(on ? 'Включена версия для слабовидящих.' : 'Обычная версия сайта.');
	}

	/* ---------- cookie consent ---------- */
	function getConsent() {
		var match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'));
		if (!match) return null;
		try {
			var data = JSON.parse(decodeURIComponent(match[1]));
			if (!data || data.v !== 1 || !data.categories) return null;
			return data;
		} catch (e) { return null; }
	}
	function setConsent(record) {
		var value = encodeURIComponent(JSON.stringify(record));
		var secure = location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = COOKIE + '=' + value + '; Max-Age=' + MAX_AGE + '; Path=/; SameSite=Lax' + secure;
	}
	function activateConsentScripts(record) {
		window.NVGSO_CONSENT = record;
		var categories = record.categories || {};
		$$('script[type="text/plain"][data-consent]').forEach(function (node) {
			var need = node.getAttribute('data-consent');
			if (!(need === 'necessary' || categories[need]) || node.dataset.activated === '1') return;
			var script = document.createElement('script');
			Array.prototype.forEach.call(node.attributes, function (attr) {
				if (['type', 'data-consent', 'data-activated'].indexOf(attr.name) === -1) script.setAttribute(attr.name, attr.value);
			});
			script.text = node.textContent;
			node.dataset.activated = '1';
			node.parentNode.insertBefore(script, node.nextSibling);
		});
		document.dispatchEvent(new CustomEvent('nvgso:consent', { detail: record }));
		if (categories.analytics) document.dispatchEvent(new CustomEvent('nvgso:consent-analytics'));
		if (categories.marketing) document.dispatchEvent(new CustomEvent('nvgso:consent-marketing'));
	}
	function showBanner() {
		var banner = $('#cookie-banner');
		if (!banner) return;
		requestAnimationFrame(function () {
			banner.classList.add('is-visible');
			var update = function () { document.body.style.paddingBottom = banner.offsetHeight + 'px'; };
			update();
			window.addEventListener('resize', update, { passive: true });
		});
	}
	function hideBanner() {
		var banner = $('#cookie-banner');
		if (!banner) return;
		banner.classList.remove('is-visible');
		document.body.style.paddingBottom = '';
		setTimeout(function () { banner.remove(); }, 300);
	}
	function openCookieModal() {
		var modal = $('#cookie-modal');
		if (!modal) return;
		var consent = getConsent();
		var current = consent ? consent.categories : { functional: true, analytics: false, marketing: false };
		current.functional = true;
		$$('[data-consent-cat]').forEach(function (input) {
			if (input.disabled) return;
			input.checked = !!current[input.dataset.consentCat];
		});
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		lockScroll();
		trap(modal, closeCookieModal);
	}
	function closeCookieModal() {
		var modal = $('#cookie-modal');
		if (!modal) return;
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		unlockScroll();
		untrap(modal);
	}
	function sendConsent(action, categories) {
		categories.functional = true;
		var existing = getConsent();
		var id = existing && existing.id ? existing.id : (window.crypto && crypto.randomUUID ? crypto.randomUUID() : 'c-' + Date.now() + '-' + Math.random().toString(16).slice(2));
		var record = { v: 1, id: id, categories: categories, action: action, ts: Date.now() };
		setConsent(record);
		activateConsentScripts(record);
		hideBanner();
		closeCookieModal();
		var body = new URLSearchParams();
		body.set('action', 'nvgso_consent');
		body.set('nonce', (window.NVGSO && NVGSO.nonce) || '');
		body.set('consent', JSON.stringify({ action: action, categories: categories, consent_id: id, page: location.href }));
		fetch((window.NVGSO && NVGSO.ajaxUrl) || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).catch(function () { toast('Не удалось записать согласие на сервере. Настройки сохранены в браузере.', 'error'); });
		toast('Настройки cookie сохранены.');
	}
	function initCookie() {
		var consent = getConsent();
		if (consent) { activateConsentScripts(consent); }
		else { setTimeout(showBanner, 600); }
	}

	/* ---------- lightbox ---------- */
	var lightboxItems = [];
	var lightboxIndex = 0;
	function openLightbox(items, index) {
		lightboxItems = items;
		lightboxIndex = index;
		var box = $('#lightbox');
		if (!box || !items.length) return;
		renderLightbox();
		box.classList.add('is-open');
		lockScroll();
		trap(box, closeLightbox);
	}
	function renderLightbox() {
		var item = lightboxItems[lightboxIndex];
		var media = $('#lightbox-media');
		var caption = $('#lightbox-caption');
		var counter = $('#lightbox-counter');
		if (!item || !media) return;
		media.innerHTML = '<img class="lightbox__img" src="' + item.src + '" alt="' + (item.title || '').replace(/"/g, '&quot;') + '">';
		caption.innerHTML = '<strong></strong><span class="lightbox__text"></span>';
		caption.querySelector('strong').textContent = item.title || '';
		caption.querySelector('.lightbox__text').textContent = item.caption ? ' ' + item.caption : '';
		counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxItems.length;
		var prev = $('[data-lightbox-prev]');
		var next = $('[data-lightbox-next]');
		if (prev) prev.disabled = lightboxItems.length < 2;
		if (next) next.disabled = lightboxItems.length < 2;
	}
	function moveLightbox(direction) {
		if (!lightboxItems.length) return;
		lightboxIndex = (lightboxIndex + direction + lightboxItems.length) % lightboxItems.length;
		renderLightbox();
	}
	function closeLightbox() {
		var box = $('#lightbox');
		if (!box) return;
		box.classList.remove('is-open');
		unlockScroll();
		untrap(box);
	}
	function initLightbox() {
		document.addEventListener('click', function (event) {
			var link = event.target.closest('[data-lightbox]');
			if (link) {
				event.preventDefault();
				var group = link.closest('[data-lightbox-group]') || document;
				var nodes = $$('[data-lightbox]', group);
				var items = nodes.map(function (node) {
					return {
						src: node.getAttribute('data-lightbox') || node.getAttribute('href'),
						title: node.getAttribute('data-title') || '',
						caption: node.getAttribute('data-caption') || ''
					};
				});
				openLightbox(items, nodes.indexOf(link));
				return;
			}
			if (event.target.closest('[data-lightbox-close]') || event.target.id === 'lightbox') closeLightbox();
			if (event.target.closest('[data-lightbox-prev]')) moveLightbox(-1);
			if (event.target.closest('[data-lightbox-next]')) moveLightbox(1);
		});
		document.addEventListener('keydown', function (event) {
			var box = $('#lightbox');
			if (!box || !box.classList.contains('is-open')) return;
			if (event.key === 'ArrowLeft') moveLightbox(-1);
			if (event.key === 'ArrowRight') moveLightbox(1);
		});
	}

	/* ---------- gallery filters ---------- */
	function initGalleryFilters() {
		$$('[data-gallery-filter]').forEach(function (button) {
			button.addEventListener('click', function () {
				var album = button.dataset.galleryFilter;
				$$('[data-gallery-filter]').forEach(function (b) { b.classList.toggle('is-active', b === button); });
				$$('[data-gallery-item]').forEach(function (item) {
					item.classList.toggle('is-hidden', album !== 'all' && item.dataset.album !== album);
				});
			});
		});
	}

	/* ---------- подменю при наведении ---------- */
	function initNavHover() {
		if (!window.matchMedia || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
		$$('.nav-item--sub').forEach(function (item) {
			var button = $('[data-submenu]', item);
			if (!button) return;
			var target = document.getElementById(button.dataset.submenu);
			if (!target) return;
			function setOpen(open) {
				target.classList.toggle('is-open', open);
				button.setAttribute('aria-expanded', String(open));
			}
			item.addEventListener('mouseenter', function () { setOpen(true); });
			item.addEventListener('mouseleave', function () { setOpen(false); });
		});
	}

	/* ---------- global click/keyboard ---------- */
	document.addEventListener('click', function (event) {
		if (!event.target.closest('.nav-item--sub')) {
			$$('.nav-item--sub .submenu.is-open').forEach(function (menu) {
				menu.classList.remove('is-open');
				var trigger = menu.parentNode.querySelector('[data-submenu]');
				if (trigger) trigger.setAttribute('aria-expanded', 'false');
			});
		}
		if (event.target.closest('[data-mobile-open]')) { openMobile(); return; }
		if (event.target.closest('[data-mobile-close]')) { closeMobile(); return; }
		var sub = event.target.closest('[data-submenu]');
		if (sub) {
			var target = document.getElementById(sub.dataset.submenu);
			if (target) {
				var isOpen = target.classList.toggle('is-open');
				sub.setAttribute('aria-expanded', String(isOpen));
			}
			return;
		}
		var mobileSub = event.target.closest('[data-mobile-sub]');
		if (mobileSub) {
			var mTarget = document.getElementById(mobileSub.dataset.mobileSub);
			if (mTarget) {
				var mOpen = mTarget.classList.toggle('is-open');
				mobileSub.setAttribute('aria-expanded', String(mOpen));
			}
			return;
		}
		if (event.target.closest('[data-search-open]')) { closeMobile(); openSearch(); return; }
		if (event.target.closest('[data-search-close]')) { closeSearch(); return; }
		if (event.target.closest('[data-a11y-toggle]')) { closeMobile(); toggleA11y(); return; }
		if (event.target.closest('[data-cookie-open]')) { openCookieModal(); return; }
		if (event.target.closest('[data-cookie-close]')) { closeCookieModal(); return; }
		var cookie = event.target.closest('[data-cookie]');
		if (cookie) {
			if (cookie.dataset.cookie === 'all') sendConsent('accept_all', { functional: true, analytics: true, marketing: true });
			else if (cookie.dataset.cookie === 'necessary') sendConsent('necessary', { functional: true, analytics: false, marketing: false });
			else if (cookie.dataset.cookie === 'settings') openCookieModal();
			return;
		}
		if (event.target.closest('[data-cookie-save]')) {
			var categories = {};
			$$('[data-consent-cat]').forEach(function (input) { categories[input.dataset.consentCat] = input.checked; });
			sendConsent('custom', categories);
			return;
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') return;
		if (mobile && mobile.classList.contains('is-open')) closeMobile();
		if (search && search.classList.contains('is-open')) closeSearch();
		var modal = $('#cookie-modal');
		if (modal && modal.classList.contains('is-open')) closeCookieModal();
	});


	/* ---------- защита от двойного тапа по шапке ---------- */
	function initTapGuard() {
		var lastTap = 0;
		$$('.brand, .hotline__num, .masthead .icon-btn, .topbar__btn, .topbar__admin').forEach(function (el) {
			el.addEventListener('touchend', function (event) {
				var now = Date.now();
				if (now - lastTap < 350) {
					event.preventDefault();
				}
				lastTap = now;
			}, { passive: false });
		});
	}



	/* ---------- проверка вложения формы обратной связи ---------- */
	function initFeedbackFile() {
		var input = document.getElementById('fb-attachment');
		if (!input) return;
		var error = document.getElementById('fb-attachment-error');
		var info = document.getElementById('fb-attachment-info');
		var max = 15 * 1024 * 1024;
		function sizeText(bytes) {
			if (bytes >= 1024 * 1024) {
				return (bytes / (1024 * 1024)).toFixed(1).replace('.', ',') + ' МБ';
			}
			return Math.max(1, Math.round(bytes / 1024)) + ' КБ';
		}
		function validate() {
			var file = input.files && input.files[0];
			if (!file) {
				if (error) error.textContent = '';
				if (info) info.textContent = '';
				input.setCustomValidity('');
				return true;
			}
			if (info) info.textContent = file.name + ' · ' + sizeText(file.size);
			var ext = (file.name.split('.').pop() || '').toLowerCase();
			if (['pdf', 'docx'].indexOf(ext) === -1) {
				if (error) error.textContent = 'Можно приложить только файл PDF или DOCX.';
				input.setCustomValidity('Можно приложить только файл PDF или DOCX.');
				return false;
			}
			if (file.size > max) {
				if (error) error.textContent = 'Размер вложения не должен превышать 15 МБ.';
				input.setCustomValidity('Размер вложения не должен превышать 15 МБ.');
				return false;
			}
			if (error) error.textContent = '';
			input.setCustomValidity('');
			return true;
		}
		input.addEventListener('change', validate);
		var form = input.closest('form');
		if (form) {
			form.addEventListener('submit', function (event) {
				if (!validate()) {
					event.preventDefault();
					input.reportValidity();
				}
			});
		}
	}


	/* ---------- локальная статистика посещаемости ---------- */
	var trafficSent = false;
	function sendTraffic() {
		if (trafficSent || !window.NVGSO || !NVGSO.ajaxUrl) return;
		var consent = getConsent();
		if (!consent || !consent.categories || !consent.categories.analytics) return;
		if (document.body.classList.contains('logged-in')) return;
		if (navigator.doNotTrack === '1' || window.doNotTrack === '1') return;
		trafficSent = true;
		var data = new URLSearchParams();
		data.set('action', 'nvgso_track');
		data.set('path', location.pathname || '/');
		data.set('ref', document.referrer || '');
		if (window.fetch) {
			fetch(NVGSO.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: data.toString(),
				keepalive: true
			}).catch(function () {});
		} else if (navigator.sendBeacon) {
			navigator.sendBeacon(NVGSO.ajaxUrl, data);
		}
	}
	function initTraffic() {
		sendTraffic();
		document.addEventListener('nvgso:consent-analytics', sendTraffic);
	}

	/* ---------- boot ---------- */
	document.addEventListener('DOMContentLoaded', function () {
		applyA11y(a11yOn());
		initNavHover();
		initCookie();
		initLightbox();
		initGalleryFilters();
		initTapGuard();
		initFeedbackFile();
		initTraffic();

		var modal = $('#cookie-modal');
		if (modal) {
			modal.addEventListener('click', function (event) {
				if (event.target === modal) closeCookieModal();
			});
		}
		var searchModal = $('#search-modal');
		if (searchModal) {
			searchModal.addEventListener('click', function (event) {
				if (event.target === searchModal) closeSearch();
			});
		}
	});
})();
