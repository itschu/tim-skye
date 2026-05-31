// Initialize AOS (Animate On Scroll)
document.addEventListener('DOMContentLoaded', function () {
	if (typeof AOS !== 'undefined') {
		AOS.init({
			duration: 800,
			easing: 'ease-in-out',
			once: true,
			offset: 100,
			disable: false,
			anchorPlacement: 'top-bottom',
			throttleDelay: 99,
			debounceDelay: 50,
		});

		// Mark AOS as initialized so other scripts won't re-init it.
		try {
			window.__AOS_INITIALIZED = true;
		} catch (e) {
			// ignore
		}
	}
});

// GSAP initialization and parallax effects
if (typeof gsap !== 'undefined') {
	// GSAP Parallax Effect for Hero and CTA Sections
	document.addEventListener('DOMContentLoaded', function () {
		// Respect user preference for reduced motion and skip on small screens
		try {
			const prefersReduced = (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) || false;
			const isSmallScreen = (window.innerWidth || document.documentElement.clientWidth) < 768;
			if (prefersReduced) {
				return;
			}
			if (isSmallScreen) {
				return;
			}
		} catch (err) {
			// If detection fails, proceed but protect with try/catch below
			console.warn('Motion/screen detection failed', err);
		}

		if (typeof ScrollTrigger !== 'undefined') {
			try {
				gsap.registerPlugin(ScrollTrigger);

				// Parallax effect for hero background shapes (0.5x scroll speed)
				const heroShapes = document.querySelectorAll('.hero-shape');

				heroShapes.forEach((shape, index) => {
					const speed = 0.5 + index * 0.1; // 0.5x, 0.6x, 0.7x

					gsap.to(shape, {
						y: () => window.innerHeight * speed,
						ease: 'none',
						scrollTrigger: {
							trigger: '.hero-section',
							start: 'top top',
							end: 'bottom top',
							scrub: true,
							invalidateOnRefresh: true,
						},
					});
				});

				// Subtle rotation for visual interest
				gsap.to('.hero-shape-1', {
					rotation: 45,
					ease: 'none',
					scrollTrigger: {
						trigger: '.hero-section',
						start: 'top top',
						end: 'bottom top',
						scrub: 2,
					},
				});

				// Parallax for final CTA section shapes (if present)
				const ctaShapes = document.querySelectorAll('.cta-section .hero-shape');
				ctaShapes.forEach((shape, index) => {
					const speed = 0.4 + index * 0.08;

					gsap.to(shape, {
						y: () => window.innerHeight * speed,
						ease: 'none',
						scrollTrigger: {
							trigger: '.cta-section',
							start: 'top bottom',
							end: 'bottom top',
							scrub: true,
							invalidateOnRefresh: true,
						},
					});
				});
			} catch (e) {
				console.warn('GSAP ScrollTrigger init failed', e);
			}
		}
	});
}

// Smooth scroll for anchor links with sticky header offset
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
	anchor.addEventListener('click', function (e) {
		const href = this.getAttribute('href');
		if (href === '#') return;
		e.preventDefault();
		const target = document.querySelector(href);
		if (target) {
			const headerOffset = 70; // Match .public-header height
			const elementPosition = target.getBoundingClientRect().top;
			const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
			window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
		}
	});
});

// Mobile menu is handled by Alpine.js in public-header.php

// Carousel initialization (placeholder)
function initCarousel() {
	try {
		const carouselEl = document.querySelector('#testimonialCarousel');
		if (carouselEl && typeof bootstrap !== 'undefined' && bootstrap.Carousel) {
			new bootstrap.Carousel(carouselEl, {
				interval: 5000,
				pause: 'hover',
				keyboard: true,
				ride: 'carousel',
			});
		}
	} catch (e) {
		console.warn('Carousel init failed', e);
	}
}

// Accordion initialization
function initAccordion() {
	const faqItems = document.querySelectorAll('.faq-item');
	faqItems.forEach((item) => {
		const header = item.querySelector('.faq-header');
		const body = item.querySelector('.faq-body');
		if (body && item.classList.contains('active')) {
			body.style.maxHeight = body.scrollHeight + 'px';
		}
		if (header) {
			header.addEventListener('click', function () {
				// Toggle only the clicked item to allow multiple open
				item.classList.toggle('active');
				if (item.classList.contains('active')) {
					// expand
					body.style.maxHeight = body.scrollHeight + 'px';
				} else {
					// collapse
					body.style.maxHeight = null;
				}
			});
		}
	});
}

// Counter animation (placeholder)
function initCounters() {
	if (typeof gsap === 'undefined') return;

	const section = document.querySelector('#statistics-section');
	if (!section) return;

	const numbers = section.querySelectorAll('.stats-number');

	function formatNumber(value, decimals) {
		if (typeof value !== 'number') value = parseFloat(value) || 0;
		if (decimals && decimals > 0) {
			return Number(value)
				.toFixed(decimals)
				.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		}
		return Math.floor(value)
			.toString()
			.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

	const observerOptions = {
		threshold: 0.3,
		rootMargin: '0px',
	};

	const observer = new IntersectionObserver((entries) => {
		entries.forEach((entry) => {
			if (entry.isIntersecting) {
				numbers.forEach((el) => {
					const raw = el.getAttribute('data-count') || '0';
					const suffix = el.getAttribute('data-suffix') || '';
					const targetValue = parseFloat(raw.replace(/,/g, '')) || 0;
					const decimals = raw.indexOf('.') !== -1 ? (raw.split('.')[1] || '').length : 0;

					// Cancel previous tweens
					gsap.killTweensOf(el);

					const obj = { val: 0 };
					gsap.to(obj, {
						val: targetValue,
						duration: 2.5,
						ease: 'power2.out',
						onUpdate() {
							const v = obj.val;
							el.textContent = formatNumber(v, decimals) + suffix;
						},
						force3D: true,
						lazy: false,
					});
				});
			} else {
				// Reset for replay
				numbers.forEach((el) => {
					const suffix = el.getAttribute('data-suffix') || '';
					el.textContent = '0' + suffix;
				});
			}
		});
	}, observerOptions);

	observer.observe(section);
}

document.addEventListener('DOMContentLoaded', function () {
	initAccordion();
	initCarousel();
	initCounters();
});

// Enhanced sticky header behavior for public pages
(function () {
	const header = document.querySelector('.public-header');
	if (!header) return;
	let lastScroll = 0;
	const headerHeight = 70;
	window.addEventListener(
		'scroll',
		function () {
			const currentScroll = window.pageYOffset;
			if (currentScroll > 10) {
				header.classList.add('scrolled');
			} else {
				header.classList.remove('scrolled');
			}
			lastScroll = currentScroll;
		},
		{ passive: true },
	);
})();

// Copy to clipboard utility
async function copyToClipboard(text) {
	try {
		if (navigator.clipboard && window.isSecureContext) {
			await navigator.clipboard.writeText(text);
			return true;
		} else {
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
		console.error('Failed to copy:', err);
		return false;
	}
}

// Performance monitoring (remove in production)
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
	let lastTime = performance.now();
	let frames = 0;

	function measureFPS() {
		const currentTime = performance.now();
		frames++;

		if (currentTime >= lastTime + 1000) {
			const fps = Math.round((frames * 1000) / (currentTime - lastTime));
			frames = 0;
			lastTime = currentTime;
		}

		requestAnimationFrame(measureFPS);
	}

	requestAnimationFrame(measureFPS);
}
