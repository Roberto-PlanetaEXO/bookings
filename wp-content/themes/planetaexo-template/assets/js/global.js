/* global.js — PlanetaEXO Theme */
(function () {
    'use strict';

    // Mobile nav toggle
    var toggle = document.querySelector('.site-nav__toggle');
    var nav    = document.querySelector('.site-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !expanded);
            nav.classList.toggle('is-open');
        });

        // Fecha ao clicar fora
        document.addEventListener('click', function (e) {
            if (!nav.contains(e.target) && !toggle.contains(e.target)) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
})();
