/**
 * EMS Motion presets — Arc-inspired subtle motion for Core PHP (vanilla JS).
 * Equivalent intent to Framer Motion presets without requiring React.
 */
(function () {
    'use strict';

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const presets = {
        pageTransition: { duration: 220, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', y: 8 },
        fadeIn: { duration: 180, easing: 'ease-out' },
        fadeUp: { duration: 260, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', y: 10 },
        scaleIn: { duration: 220, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', scale: 0.97 },
        staggerContainer: { stagger: 35 },
        slidePanel: { duration: 240, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', x: 16 },
        modalTransition: { duration: 200, easing: 'ease-out', scale: 0.98 },
    };

    function animateIn(el, presetName) {
        if (!el || reduced || !el.animate) {
            if (el) el.style.opacity = '1';
            return;
        }
        const p = presets[presetName] || presets.fadeUp;
        const keyframes = [{ opacity: 0 }, { opacity: 1 }];
        if (p.y) {
            keyframes[0].transform = 'translateY(' + p.y + 'px)';
            keyframes[1].transform = 'none';
        }
        if (p.x) {
            keyframes[0].transform = 'translateX(' + p.x + 'px)';
            keyframes[1].transform = 'none';
        }
        if (p.scale) {
            keyframes[0].transform = 'scale(' + p.scale + ')';
            keyframes[1].transform = 'none';
        }
        el.animate(keyframes, { duration: p.duration || 220, easing: p.easing || 'ease', fill: 'both' });
    }

    function stagger(container, itemSelector) {
        if (!container) return;
        const items = container.querySelectorAll(itemSelector || '.stagger-item, .metric-card, .ems-card');
        items.forEach(function (item, index) {
            if (reduced) {
                item.style.opacity = '1';
                return;
            }
            item.style.opacity = '0';
            setTimeout(function () {
                animateIn(item, 'fadeUp');
                item.style.opacity = '1';
            }, index * (presets.staggerContainer.stagger || 35));
        });
    }

    function countUp(el, target, duration) {
        if (!el) return;
        target = Number(target) || 0;
        if (reduced) {
            el.textContent = String(target);
            return;
        }
        const start = performance.now();
        duration = duration || 500;
        function frame(now) {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = String(Math.round(target * eased));
            if (t < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }

    window.EMSMotion = {
        presets: presets,
        reduced: reduced,
        animateIn: animateIn,
        stagger: stagger,
        countUp: countUp,
    };

    document.addEventListener('DOMContentLoaded', function () {
        const main = document.querySelector('.app-content');
        if (main) animateIn(main, 'pageTransition');
        stagger(document.querySelector('.metrics-row'));
        document.querySelectorAll('[data-count-to]').forEach(function (el) {
            countUp(el, el.getAttribute('data-count-to'));
        });
    });
})();
