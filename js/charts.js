/* Gráficas en HTML: barras horizontales, columnas por día, barras agrupadas y
   rangos de precio. Las posiciones son porcentajes, así se adaptan a cualquier
   ancho sin volver a dibujar. Cada marca tiene su tooltip con el mouse o el teclado. */

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

const compact = new Intl.NumberFormat('es-MX', { notation: 'compact', maximumFractionDigits: 1 });

export function compactMoney(value) {
    return '$' + (Math.abs(value) >= 10000 ? compact.format(value) : Math.round(value).toLocaleString('es-MX'));
}

/* Escala "bonita" para el eje: 0 a un múltiplo de 1, 2, 2.5 o 5 × 10^n. */
function niceMax(value, ticks) {
    if (value <= 0) return 1;
    const raw = value / ticks;
    const power = Math.pow(10, Math.floor(Math.log10(raw)));
    const step = [1, 2, 2.5, 5, 10].map(function (m) { return m * power; }).find(function (s) { return s >= raw; });
    return step * ticks;
}

function percent(value, max) {
    return Math.max(0, Math.min(100, value / max * 100)) + '%';
}

/* ---------- Tooltip compartido por la gráfica ---------- */
function attachTooltip(root) {
    const tip = el('div', 'chart-tip');
    tip.hidden = true;
    tip.setAttribute('role', 'status');
    root.append(tip);

    function place(target, clientX) {
        const box = root.getBoundingClientRect();
        const mark = target.getBoundingClientRect();
        const x = (clientX !== undefined ? clientX : mark.left + mark.width / 2) - box.left;
        tip.style.left = Math.max(8, Math.min(box.width - tip.offsetWidth - 8, x - tip.offsetWidth / 2)) + 'px';
        tip.style.top = Math.max(0, mark.top - box.top - tip.offsetHeight - 8) + 'px';
    }

    function open(target, clientX) {
        const lines = target.chartTip;
        if (!lines) return;
        tip.replaceChildren(...lines.map(function (line, i) {
            const row = el('div', i === 0 ? 'chart-tip-title' : 'chart-tip-row');
            if (line.swatch) row.append(el('i', 'swatch-dot ' + line.swatch));
            row.append(el('span', '', line.text));
            if (line.value !== undefined) row.append(el('strong', '', line.value));
            return row;
        }));
        tip.hidden = false;
        place(target, clientX);
        root.querySelectorAll('.is-active').forEach(function (m) { m.classList.remove('is-active'); });
        target.classList.add('is-active');
    }

    function close() {
        tip.hidden = true;
        root.querySelectorAll('.is-active').forEach(function (m) { m.classList.remove('is-active'); });
    }

    root.addEventListener('pointermove', function (e) {
        const target = e.target.closest('[data-tip]');
        if (target) open(target, e.clientX); else close();
    });
    root.addEventListener('pointerleave', close);
    root.addEventListener('focusin', function (e) {
        const target = e.target.closest('[data-tip]');
        if (target) open(target);
    });
    root.addEventListener('focusout', close);
}

function tipTarget(node, lines, label) {
    node.dataset.tip = '';
    node.chartTip = lines;
    node.tabIndex = 0;
    node.setAttribute('aria-label', label || lines.map(function (l) {
        return l.text + (l.value !== undefined ? ' ' + l.value : '');
    }).join(', '));
    return node;
}

export function legend(series) {
    const box = el('div', 'chart-legend');
    series.forEach(function (s) {
        const item = el('span', 'chart-legend-item');
        item.append(el('i', 'swatch-dot ' + s.css), el('span', '', s.name));
        box.append(item);
    });
    return box;
}

/* ---------- Barras horizontales de una serie ---------- */
export function barChart(rows, options) {
    const format = options.format;
    const max = Math.max.apply(null, rows.map(function (r) { return r.value; }).concat([1]));
    const root = el('div', 'chart chart-bars');
    root.setAttribute('role', 'group');
    root.setAttribute('aria-label', options.label);

    rows.forEach(function (r) {
        const row = el('div', 'bar-row');
        const name = el('div', 'bar-label');
        name.append(el('span', '', r.label));
        if (r.sub) name.append(el('small', 'muted', r.sub));
        const track = el('div', 'bar-track');
        const bar = el('div', 'bar-mark ' + (options.css || 'series-1'));
        bar.style.width = percent(r.value, max);
        track.append(tipTarget(bar, [{ text: r.label }, { text: options.valueName || 'Total', value: format(r.value) }].concat(r.tip || [])));
        track.append(el('span', 'bar-value', format(r.value)));
        row.append(name, track);
        root.append(row);
    });
    attachTooltip(root);
    return root;
}

/* ---------- Columnas por día con eje Y ---------- */
export function columnChart(points, options) {
    const format = options.format;
    const top = niceMax(Math.max.apply(null, points.map(function (p) { return p.value; })), 4);
    const root = el('div', 'chart chart-columns');
    root.setAttribute('role', 'group');
    root.setAttribute('aria-label', options.label);

    const plot = el('div', 'col-plot');
    const grid = el('div', 'col-grid');
    for (let i = 4; i >= 0; i--) {
        const line = el('div', 'col-gridline');
        line.append(el('span', 'col-tick', options.axisFormat(top * i / 4)));
        grid.append(line);
    }
    plot.append(grid);

    const bars = el('div', 'col-bars');
    const peak = points.reduce(function (best, p) { return p.value > best.value ? p : best; }, points[0]);
    points.forEach(function (p, i) {
        const slotEl = el('div', 'col-slot');
        const mark = el('div', 'col-mark ' + (options.css || 'series-1'));
        mark.style.height = p.value > 0 ? 'max(2px, ' + percent(p.value, top) + ')' : '0';
        if (p === peak && p.value > 0) {
            const peakLabel = el('span', 'col-peak', options.axisFormat(p.value));
            peakLabel.style.bottom = percent(p.value, top);
            slotEl.append(peakLabel);
        }
        slotEl.append(mark);
        tipTarget(slotEl, [{ text: p.label }].concat(p.tip || [{ text: 'Total', value: format(p.value) }]));
        bars.append(slotEl);
        if (i % options.labelEvery === options.labelEvery - 1 || i === points.length - 1) {
            slotEl.dataset.axis = p.short;
        }
    });
    plot.append(bars);
    root.append(plot);
    attachTooltip(root);
    return root;
}

/* ---------- Barras horizontales agrupadas (varias series por categoría) ---------- */
export function groupedBars(groups, series, options) {
    const format = options.format;
    const max = Math.max.apply(null, groups.flatMap(function (g) { return g.values; }).concat([1]));
    const root = el('div', 'chart chart-groups');
    root.setAttribute('role', 'group');
    root.setAttribute('aria-label', options.label);
    root.append(legend(series));

    groups.forEach(function (g) {
        const block = el('div', 'group-block');
        const head = el('div', 'group-head');
        head.append(el('strong', '', g.label));
        if (g.note) head.append(el('span', 'group-note', g.note));
        block.append(head);
        series.forEach(function (s, i) {
            const track = el('div', 'bar-track thin');
            const bar = el('div', 'bar-mark ' + s.css);
            bar.style.width = percent(g.values[i], max);
            track.append(tipTarget(bar, [{ text: g.label }].concat(series.map(function (x, j) {
                return { text: x.name, value: format(g.values[j]), swatch: x.css };
            }))));
            track.append(el('span', 'bar-value', format(g.values[i])));
            block.append(track);
        });
        root.append(block);
    });
    attachTooltip(root);
    return root;
}

/* ---------- Rango de precios: puntos de la competencia contra los nuestros ---------- */
export function priceRanges(rows, series, options) {
    const format = options.format;
    const top = niceMax(Math.max.apply(null, rows.map(function (r) { return r.max; })), 4);
    const root = el('div', 'chart chart-ranges');
    root.setAttribute('role', 'group');
    root.setAttribute('aria-label', options.label);
    root.append(legend(series));

    const axis = el('div', 'range-row range-axis');
    axis.append(el('div', 'range-label'));
    const ticks = el('div', 'range-track');
    for (let i = 0; i <= 4; i++) {
        const tick = el('span', 'range-tick', compactMoney(top * i / 4));
        tick.style.left = percent(top * i / 4, top);
        ticks.append(tick);
    }
    axis.append(ticks);
    root.append(axis);

    rows.forEach(function (r) {
        const row = el('div', 'range-row');
        const name = el('a', 'range-label', r.label);
        name.href = r.href;
        const track = el('div', 'range-track');

        const span = el('div', 'range-span');
        span.style.left = percent(r.min, top);
        span.style.width = 'calc(' + percent(r.max, top) + ' - ' + percent(r.min, top) + ')';
        track.append(span);

        r.points.forEach(function (p) {
            const dot = el('span', 'range-dot ' + p.css);
            dot.style.left = percent(p.value, top);
            track.append(tipTarget(dot, [{ text: r.label }].concat(r.tip(p))));
        });
        row.append(name, track);
        root.append(row);
    });
    attachTooltip(root);
    return root;
}

export function chartEmpty(text) {
    return el('p', 'chart-empty muted', text);
}
