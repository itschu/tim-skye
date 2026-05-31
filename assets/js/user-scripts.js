// Alpine.js Global Data Store
document.addEventListener('alpine:init', () => {
	Alpine.store('app', {
		sidebarOpen: false,
		mobileTab: 'home',
		currency: 'USD',
		showBalance: true,
		toggleSidebar() {
			this.sidebarOpen = !this.sidebarOpen;
		},
		closeSidebar() {
			this.sidebarOpen = false;
		},
	});
});

// Sticky Header Fallback (works with or without Alpine.js)
(function () {
	const tickerHeight = 40;
	const headers = document.querySelectorAll('.sticky-header, .sticky-header-mobile');

	function updateHeaderSticky() {
		const isStuck = window.scrollY > tickerHeight;
		headers.forEach((header) => {
			if (isStuck) {
				header.classList.add('is-stuck');
			} else {
				header.classList.remove('is-stuck');
			}
		});
	}

	// Run on load
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', updateHeaderSticky);
	} else {
		updateHeaderSticky();
	}

	// Run on scroll (throttled)
	let ticking = false;
	window.addEventListener(
		'scroll',
		function () {
			if (!ticking) {
				window.requestAnimationFrame(function () {
					updateHeaderSticky();
					ticking = false;
				});
				ticking = true;
			}
		},
		{ passive: true },
	);
})();

// Utility Functions

// Countdown Timer Function
function createCountdownTimer(targetDate, onTick) {
	// Allow onTick to be a callback function, a DOM element, or a selector string
	let tickCallback = null;
	if (typeof onTick === 'function') {
		tickCallback = onTick;
	} else if (typeof onTick === 'string') {
		const el = document.querySelector(onTick);
		if (el)
			tickCallback = (s, expired) => {
				el.textContent = s;
			};
	} else if (onTick instanceof Element) {
		const el = onTick;
		tickCallback = (s, expired) => {
			el.textContent = s;
		};
	}

	function computeFormatted() {
		const now = new Date().getTime();
		const distance = new Date(targetDate).getTime() - now;

		if (distance <= 0) {
			return { formatted: '00:00:00', expired: true };
		}

		const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
		const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
		const seconds = Math.floor((distance % (1000 * 60)) / 1000);

		const formatted = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
		return { formatted, expired: false };
	}

	// Immediately fire one tick so UI updates without 1s delay
	const first = computeFormatted();
	if (tickCallback) tickCallback(first.formatted, first.expired);

	const interval = setInterval(() => {
		const tick = computeFormatted();
		if (tickCallback) tickCallback(tick.formatted, tick.expired);

		if (tick.expired) {
			clearInterval(interval);
		}
	}, 1000);

	return interval;
}

// Fee Calculator Function
function calculateFee(amount, feePercentage) {
	const fee = amount * (feePercentage / 100);
	const net = amount - fee;
	return {
		fee: fee.toFixed(2),
		net: net.toFixed(2),
		total: amount,
	};
}

// Copy to Clipboard Function
async function copyToClipboard(text) {
	try {
		if (navigator.clipboard && window.isSecureContext) {
			await navigator.clipboard.writeText(text);
			return true;
		} else {
			// Fallback for older browsers
			const textArea = document.createElement('textarea');
			textArea.value = text;
			textArea.style.position = 'fixed';
			document.body.appendChild(textArea);
			textArea.focus();
			textArea.select();
			const successful = document.execCommand('copy');
			document.body.removeChild(textArea);
			return successful;
		}
	} catch (err) {
		console.error('Failed to copy: ', err);
		return false;
	}
}

// Currency Formatter Function
function formatCurrency(amount, currencyCode) {
	const formatter = new Intl.NumberFormat('en-US', {
		style: 'currency',
		currency: currencyCode,
	});
	return formatter.format(amount);
}

// Time Ago Function
function timeAgo(dateString) {
	const date = new Date(dateString);
	const now = new Date();
	const seconds = Math.floor((now - date) / 1000);

	let interval = seconds / 31536000;
	if (interval > 1) {
		return Math.floor(interval) + ' years ago';
	}

	interval = seconds / 2592000;
	if (interval > 1) {
		return Math.floor(interval) + ' months ago';
	}

	interval = seconds / 86400;
	if (interval > 1) {
		return Math.floor(interval) + ' days ago';
	}

	interval = seconds / 3600;
	if (interval > 1) {
		return Math.floor(interval) + ' hours ago';
	}

	interval = seconds / 60;
	if (interval > 1) {
		return Math.floor(interval) + ' minutes ago';
	}

	return Math.floor(seconds) + ' seconds ago';
}

// Modal Helper Functions
const modalHelpers = {
	open(modalId) {
		const modal = document.getElementById(modalId);
		if (modal) {
			const bsModal = new bootstrap.Modal(modal);
			bsModal.show();
		}
	},

	close(modalId) {
		const modal = document.getElementById(modalId);
		if (modal) {
			const bsModal = bootstrap.Modal.getInstance(modal);
			if (bsModal) {
				bsModal.hide();
			}
		}
	},
};
// Form submit listener for button loading states
document.addEventListener('DOMContentLoaded', () => {
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
	// Initialize swipe-to-dismiss for user alerts after DOM is ready
	try {
		initSwipeToDismissUser('.p-3.p-md-4.fade-in');
	} catch (e) {}
});

// Swipe-to-dismiss for user alerts (touch devices)
function initSwipeToDismissUser(containerSelector) {
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
					// If Bootstrap Alert is available, use its close method
					try {
						if (window.bootstrap && bootstrap.Alert) {
							bootstrap.Alert.getOrCreateInstance(alertEl).close();
							return;
						}
					} catch (e) {}

					// Fallback animation
					alertEl.style.transition = 'transform 0.25s ease, opacity 0.25s ease';
					alertEl.style.transform = 'translateX(120%)';
					alertEl.style.opacity = '0';
					setTimeout(() => {
						alertEl.style.display = 'none';
					}, 260);
				}
			});
		};

		Array.from(container.querySelectorAll('.alert.alert-dismissible')).forEach(attach);

		const mo = new MutationObserver((mutations) => {
			mutations.forEach((m) => {
				m.addedNodes.forEach((n) => {
					if (n.nodeType === 1 && n.classList && n.classList.contains('alert')) attach(n);
				});
			});
		});
		mo.observe(container, { childList: true, subtree: true });
	} catch (e) {}
}

// Initialization moved into DOMContentLoaded handler above
