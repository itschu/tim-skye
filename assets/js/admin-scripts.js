document.addEventListener('alpine:init', () => {
	Alpine.store('admin', {
		sidebarOpen: false,
		toggleSidebar() {
			this.sidebarOpen = !this.sidebarOpen;
		},
		closeSidebar() {
			this.sidebarOpen = false;
		},
	});
});

// Body scroll lock helpers
function lockBodyScroll() {
	try {
		document.body.style.overflow = 'hidden';
	} catch (e) {}
}
function unlockBodyScroll() {
	try {
		document.body.style.overflow = '';
	} catch (e) {}
}

// Copy to clipboard helper
function copyToClipboard(text, onSuccess, onError) {
	if (!navigator.clipboard) {
		if (typeof onError === 'function') onError(new Error('Clipboard API unavailable'));
		return;
	}
	navigator.clipboard
		.writeText(String(text || ''))
		.then(() => {
			if (typeof onSuccess === 'function') onSuccess();
		})
		.catch((err) => {
			if (typeof onError === 'function') onError(err);
		});
}

// Sparkline entrance animation
function animateSparklines() {
	try {
		const bars = Array.from(document.querySelectorAll('.sparkline-bar'));
		bars.forEach((el, i) => {
			const original = el.style.height || getComputedStyle(el).height || '';
			// store original percentage or px height in data attribute
			el.dataset._origHeight = original;
			el.style.height = '0%';
			setTimeout(() => {
				el.style.transition = 'height 0.4s ease';
				el.style.height = el.dataset._origHeight || '';
			}, 50 * i);
		});
	} catch (e) {}
}

// Modal helpers (Bootstrap 5) - keep for backward compatibility
function openModal(modalId) {
	const el = document.getElementById(modalId);
	if (!el || !window.bootstrap) return;
	const m = new bootstrap.Modal(el);
	m.show();
}
function closeModal(modalId) {
	const el = document.getElementById(modalId);
	if (!el || !window.bootstrap) return;
	const m = bootstrap.Modal.getInstance(el);
	if (m) m.hide();
}
function populateModal(data) {
	if (!data || typeof data !== 'object') return;
	Object.keys(data).forEach((k) => {
		const el = document.querySelector('#' + k);
		if (el) el.value = data[k];
	});
}

// Table utilities
function sortTable(columnIndex, tableId) {
	const table = document.getElementById(tableId);
	if (!table) return;
	const tbody = table.tBodies[0];
	const rows = Array.from(tbody.rows);
	const asc = table.getAttribute('data-sort-dir') !== 'asc';
	rows.sort((a, b) => {
		const A = a.cells[columnIndex].innerText.trim();
		const B = b.cells[columnIndex].innerText.trim();
		return asc ? A.localeCompare(B, undefined, { numeric: true }) : B.localeCompare(A, undefined, { numeric: true });
	});
	rows.forEach((r) => tbody.appendChild(r));
	table.setAttribute('data-sort-dir', asc ? 'asc' : 'desc');
}
function filterTable(searchTerm, tableId) {
	const term = String(searchTerm).toLowerCase();
	const table = document.getElementById(tableId);
	if (!table) return;
	const rows = Array.from(table.tBodies[0].rows);
	rows.forEach((r) => {
		r.style.display = r.innerText.toLowerCase().includes(term) ? '' : 'none';
	});
}
function toggleAllCheckboxes(checked, className) {
	document.querySelectorAll('.' + className).forEach((cb) => {
		cb.checked = !!checked;
	});
}

// Auto-dismiss alerts, medium-zoom init, and sparkline animation
document.addEventListener('DOMContentLoaded', () => {
	// Medium-zoom init (kept here to centralize)
	try {
		if (window.mediumZoom) mediumZoom('[data-zoomable]', { background: '#000', margin: 24 });
	} catch (e) {}

	// Auto-dismiss alerts after 10s
	try {
		setTimeout(function () {
			var alerts = document.querySelectorAll('.alert-dismissible');
			alerts.forEach(function (a) {
				try {
					if (window.bootstrap && bootstrap.Alert) {
						var bsAlert = new bootstrap.Alert(a);
						bsAlert.close();
					} else {
						a.style.display = 'none';
					}
				} catch (e) {
					a.style.display = 'none';
				}
			});
		}, 10000);
	} catch (e) {}

	// Swipe-to-dismiss helper for touch devices
	function initSwipeToDismiss(containerSelector) {
		try {
			const container = document.querySelector(containerSelector);
			if (!container) return;
			const attach = (alertEl) => {
				if (alertEl._swipeAttached) return;
				alertEl._swipeAttached = true;
				let startX = 0;
				alertEl.addEventListener('touchstart', (ev) => {
					startX = ev.touches[0].clientX || 0;
				});
				alertEl.addEventListener('touchend', (ev) => {
					const endX = (ev.changedTouches && ev.changedTouches[0] && ev.changedTouches[0].clientX) || 0;
					const deltaX = endX - startX;
					if (deltaX > 80) {
						alertEl.style.transition = 'transform 0.25s ease, opacity 0.25s ease';
						alertEl.style.transform = 'translateX(120%)';
						alertEl.style.opacity = '0';
						setTimeout(() => {
							alertEl.style.display = 'none';
						}, 260);
					}
				});
			};

			Array.from(container.querySelectorAll('.alert')).forEach(attach);

			// Observe new alerts inserted dynamically
			const mo = new MutationObserver((mutations) => {
				mutations.forEach((m) => {
					m.addedNodes.forEach((n) => {
						if (n.nodeType === 1 && n.classList && n.classList.contains('alert')) {
							attach(n);
						}
					});
				});
			});
			mo.observe(container, { childList: true, subtree: true });
		} catch (e) {}
	}

	// Run sparkline entrance animation
	animateSparklines();

	// Initialize swipe-to-dismiss on admin alerts
	try {
		initSwipeToDismiss('.alert-fixed');
	} catch (e) {}

	// Form submit listener for button loading states
	document.addEventListener('submit', (e) => {
		const form = e.target;

		// Skip GET forms (search/filter)
		if (form.method.toLowerCase() === 'get') {
			return;
		}

		// Skip forms marked with data-no-spinner
		if (form.hasAttribute('data-no-spinner')) {
			return;
		}

		// Skip search/filter endpoints
		const action = form.getAttribute('action') || '';
		if (action.toLowerCase().includes('search') || action.toLowerCase().includes('filter')) {
			return;
		}

		// Find the submit button
		const btn = form.querySelector('[type="submit"], button:not([type="button"]):not([type="reset"]):not([type])');

		// Skip if no button found or button has data-no-spinner
		if (!btn || btn.hasAttribute('data-no-spinner')) {
			return;
		}

		// Disable button and show spinner
		btn.disabled = true;
		btn.classList.add('btn-loading');
		btn.innerHTML = '<span class="btn-spinner"></span> Processing…';
	});
});

// Expose helpers for inline Alpine components and pages
window.adminHelpers = {
	openModal,
	closeModal,
	populateModal,
	sortTable,
	filterTable,
	toggleAllCheckboxes,
	lockBodyScroll,
	unlockBodyScroll,
	copyToClipboard,
	animateSparklines,
};
