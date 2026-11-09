/* DAILY — recorrido animado (views/site/partials/live.blade.php): contadores, gráficos, filtros de demostración y reproducción automática.
   Los valores finales vienen en el HTML; aquí solo se anima hacia ellos. */
(function () {
	'use strict';
	var doc = document;
	var $$ = function (s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); };
	var figs = $$('.s-live');
	var stage = doc.querySelector('[data-tour]');
	if (!figs.length || !stage) { return; }
	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
	var DUR = 1200;

	function fmt(v, d, p, s) {
		var a = Math.abs(v).toFixed(d).split('.');
		a[0] = a[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		return p + (v < 0 && Math.abs(v) >= Math.pow(10, -d) / 2 ? '-' : '') + a.join('.') + s;
	}
	var ease = function (t) { return 1 - Math.pow(1 - t, 3); };
	var meta = function (el) { return { v: parseFloat(el.getAttribute('data-v')) || 0, d: +el.getAttribute('data-d') || 0, p: el.getAttribute('data-p') || '', s: el.getAttribute('data-s') || '' }; };

	/* Interpolación de números: un solo requestAnimationFrame por vista */
	function Board(fig) {
		this.fig = fig;
		this.lv = fig.querySelector('.lv');
		this.tweens = [];
		this.raf = 0;
		this.lv.classList.add('lv-js');
	}
	Board.prototype.tween = function (el, from, to, delay, dur) {
		this.tweens = this.tweens.filter(function (t) { return t.el !== el; });
		this.tweens.push({ el: el, from: from, to: to, t0: performance.now() + (delay || 0), dur: dur || DUR, m: meta(el) });
		el.textContent = fmt(from, +el.getAttribute('data-d') || 0, el.getAttribute('data-p') || '', el.getAttribute('data-s') || '');
		if (!this.raf) { this.raf = requestAnimationFrame(this.tick.bind(this)); }
	};
	Board.prototype.tick = function (now) {
		this.tweens = this.tweens.filter(function (t) {
			var k = Math.min(1, Math.max(0, (now - t.t0) / t.dur));
			t.el.textContent = fmt(t.from + (t.to - t.from) * ease(k), t.m.d, t.m.p, t.m.s);
			return k < 1;
		});
		this.raf = this.tweens.length ? requestAnimationFrame(this.tick.bind(this)) : 0;
	};
	Board.prototype.stop = function () {
		if (this.raf) { cancelAnimationFrame(this.raf); this.raf = 0; }
		this.tweens.forEach(function (t) { t.el.textContent = fmt(t.to, t.m.d, t.m.p, t.m.s); });
		this.tweens = [];
	};
	Board.prototype.zero = function () {
		$$('.lv-num', this.lv).forEach(function (el) { var m = meta(el); el.textContent = fmt(0, m.d, m.p, m.s); });
	};
	Board.prototype.play = function () {
		var self = this, lv = this.lv;
		this.stop();
		this.played = true;
		if (reduce.matches) { lv.classList.add('is-play'); return; }
		lv.classList.add('is-reset');
		lv.classList.remove('is-play');
		this.zero();
		void lv.offsetWidth; // aplica el estado inicial sin transición
		lv.classList.remove('is-reset');
		requestAnimationFrame(function () {
			lv.classList.add('is-play');
			$$('.lv-num', lv).forEach(function (el, i) { self.tween(el, 0, meta(el).v, 80 + Math.min(i * 40, 480)); });
		});
	};

	var boards = figs.map(function (f) { var b = new Board(f); f._board = b; return b; });
	var activeBoard = function () { var p = stage.querySelector('.s-tour__panel.is-active .s-live'); return p && p._board; };
	if (reduce.matches) { boards.forEach(function (b) { b.lv.classList.add('is-play'); }); } else { boards.forEach(function (b) { b.zero(); }); }

	/* Se anima al entrar en pantalla y se detiene al salir */
	var visible = false;
	if ('IntersectionObserver' in window) {
		// Se observa la vista (no el escenario): en móvil queda debajo del texto
		var io = new IntersectionObserver(function (es) {
			es.forEach(function (e) { e.target._in = e.isIntersecting; });
			var b = activeBoard();
			if (!b) { return; }
			visible = !!b.fig._in;
			if (visible && !b.played) { b.play(); }
			if (!visible) { b.stop(); }
		}, { rootMargin: '0px 0px -18% 0px' });
		figs.forEach(function (f) { io.observe(f); });
	} else {
		visible = true;
		boards.forEach(function (b) { b.play(); });
	}

	/* Cambio de pestaña: el panel nuevo entra con fundido (CSS) y su vista se reconstruye */
	stage.addEventListener('s-tour:show', function (e) {
		var fig = e.target.querySelector('.s-live');
		boards.forEach(function (b) { if (b.fig !== fig) { b.stop(); b.played = false; } });
		if (fig && '_in' in fig) { visible = fig._in; }
		if (fig && visible) { fig._board.play(); }
	});

	figs.forEach(function (fig) {
		var b = fig._board;
		fig.querySelector('.s-live__replay').addEventListener('click', function () { b.play(); });

		/* Filtro de demostración: métrica del Top 5 (ventas netas / unidades) */
		$$('.lv-seg', fig).forEach(function (seg) {
			var card = seg.closest('.lv-card'), list = card.querySelector('.lv-hb');
			seg.addEventListener('click', function (e) {
				var btn = e.target.closest('button[data-mode]');
				if (!btn || btn.getAttribute('aria-pressed') === 'true') { return; }
				var m = +btn.getAttribute('data-mode');
				$$('button', seg).forEach(function (x) { x.setAttribute('aria-pressed', String(x === btn)); });
				var rows = $$('li', list), vals = rows.map(function (li) { return JSON.parse(li.getAttribute('data-vals'))[m]; });
				var max = Math.max.apply(null, vals) || 1;
				list.classList.add('is-switch');
				rows.forEach(function (li, i) {
					var num = li.querySelector('.lv-num');
					num.setAttribute('data-v', vals[i]);
					num.setAttribute('data-p', JSON.parse(li.getAttribute('data-ps'))[m] || '');
					num.setAttribute('data-d', JSON.parse(li.getAttribute('data-ds'))[m] || 0);
					li.querySelector('.lv-fill').style.setProperty('--w', (vals[i] / max).toFixed(4));
					if (reduce.matches) { num.textContent = fmt(vals[i], +num.getAttribute('data-d'), num.getAttribute('data-p'), ''); } else { b.tween(num, 0, vals[i], i * 50, 900); }
				});
			});
		});

		/* Filtro de demostración: departamento del donut */
		$$('.lv-donut', fig).forEach(function (dn) {
			var c = dn.querySelector('.lv-donut__c'), num = c.querySelector('.lv-num'), sub = c.querySelector('small');
			var btns = $$('button[data-seg]', dn);
			btns.forEach(function (btn) {
				btn.addEventListener('click', function () {
					var on = btn.getAttribute('aria-pressed') !== 'true';
					btns.forEach(function (x) { x.setAttribute('aria-pressed', String(on && x === btn)); });
					$$('.lv-seg-arc', dn).forEach(function (a) { a.classList.toggle('is-on', on && a.getAttribute('data-seg') === btn.getAttribute('data-seg')); });
					if (on) { dn.setAttribute('data-picked', ''); } else { dn.removeAttribute('data-picked'); }
					var to = parseFloat(on ? btn.getAttribute('data-v') : c.getAttribute('data-total-v'));
					num.setAttribute('data-v', to);
					sub.textContent = on ? parseFloat(btn.getAttribute('data-pct')).toFixed(1) + '%' : c.getAttribute('data-total-n');
					if (reduce.matches) { num.textContent = fmt(to, 0, '$', ''); } else { b.tween(num, 0, to, 0, 900); }
				});
			});
		});
	});

	/* Reproducción automática opcional: recorre las siete pestañas sin cambiar valores */
	var auto = doc.querySelector('.s-tour__auto');
	var tabs = $$('.s-tour__tabs [role="tab"]');
	var timer = 0, hover = false, byAuto = false;
	var setAuto = function (on) {
		auto.setAttribute('aria-pressed', String(on));
		clearInterval(timer);
		timer = on ? setInterval(function () {
			if (!visible || hover || doc.hidden) { return; }
			var i = tabs.findIndex(function (t) { return t.getAttribute('aria-selected') === 'true'; });
			byAuto = true;
			tabs[(i + 1) % tabs.length].click();
			byAuto = false;
		}, 6500) : 0;
	};
	if (auto) {
		auto.addEventListener('click', function () { setAuto(auto.getAttribute('aria-pressed') !== 'true'); });
		tabs.forEach(function (t) {
			t.addEventListener('click', function () { if (!byAuto && timer) { setAuto(false); } });
			t.addEventListener('keydown', function () { if (timer) { setAuto(false); } });
		});
		stage.addEventListener('pointerenter', function () { hover = true; });
		stage.addEventListener('pointerleave', function () { hover = false; });
	}
})();
