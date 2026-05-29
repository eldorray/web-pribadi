import './bootstrap';

/**
 * Trigger blur-reveal on elements that scroll into view.
 * (top of page items get auto-revealed by their inline delay class.)
 */
function setupScrollReveal() {
    const els = document.querySelectorAll('[data-reveal]');
    if (els.length === 0) return;

    if (!('IntersectionObserver' in window)) {
        els.forEach((el) => el.classList.add('animate-reveal'));
        return;
    }

    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-reveal');
                    io.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
    );

    els.forEach((el) => io.observe(el));
}

document.addEventListener('DOMContentLoaded', setupScrollReveal);
document.addEventListener('livewire:navigated', setupScrollReveal);
