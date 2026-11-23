/* Escáner de la app: lee el código de barras de una etiqueta, muestra las
   existencias por sucursal y compara el precio con el mercado. */
import { api } from '../api.js';
import { $, clone, field, fill, show, slot, money, toast, formatDate, setTitle, sizeLabel } from '../ui.js';
import { productArt } from '../illustrations.js';
import { barChart } from '../charts.js';
import { openMiniCart } from '../minicart.js';
import { matchScore, productText } from '../search.js';
import { addToCart, findByCode, isOutlet, isPriority, physicalStores, salePrice, state, stockAt, unitPrice } from '../store.js';

const FORMATS = ['ean_13', 'ean_8', 'upc_a', 'code_128', 'qr_code'];
const history = [];
let marketData = null;

function stopCamera(session) {
    if (session.frame) cancelAnimationFrame(session.frame);
    if (session.stream) session.stream.getTracks().forEach(function (t) { t.stop(); });
    session.stream = null;
    session.frame = null;
}

async function createDetector() {
    if (!('BarcodeDetector' in window)) return null;
    try {
        const supported = await window.BarcodeDetector.getSupportedFormats();
        const formats = FORMATS.filter(function (f) { return supported.indexOf(f) >= 0; });
        return formats.length ? new window.BarcodeDetector({ formats: formats }) : null;
    } catch (e) {
        return null;
    }
}

function stockRow(storeName, qty) {
    const row = fill(clone('tpl-scan-stock'), {
        store: storeName,
        status: qty > 0 ? (qty <= 5 ? 'Últimas ' + qty : qty + ' disponibles') : 'Sin existencias'
    });
    field(row, 'status').className = qty > 0 ? 'ok-text' : 'muted';
    return row;
}

async function loadMarket() {
    if (marketData === null) {
        marketData = await api('/market');
    }
    return marketData;
}

async function marketSection(target, p) {
    if (!isPriority()) {
        target.replaceWith(clone('tpl-scan-locked'));
        return;
    }
    let data;
    try {
        data = await loadMarket();
    } catch (err) {
        target.textContent = err.message;
        return;
    }
    const m = data.products.find(function (x) { return x.id_product === p.id_product; });
    const box = clone('tpl-scan-market');
    if (!m) {
        fill(box, { verdict: 'Todavía no tenemos precios de la competencia para este producto.' });
        target.replaceWith(box);
        return;
    }
    const yours = isOutlet(p) ? salePrice(p) : m.member_price;
    const saving = m.market_average - yours;
    fill(box, {
        verdict: saving > 0
            ? 'Con nosotros te ahorras ' + money.format(saving) + ' (' + Math.round(saving / m.market_average * 100) + ' %) frente al promedio del mercado.'
            : 'En el mercado está ' + money.format(-saving) + ' más barato en promedio.',
        date: 'Precios de lista consultados el ' + formatDate(data.captured_on) + '.'
    });
    field(box, 'verdict').classList.add(saving > 0 ? 'ok-text' : 'bad-text');

    const rows = [{ label: 'Tu precio JAM', value: yours }, { label: 'Precio de lista JAM', value: m.our_price }]
        .concat(m.offers.map(function (o) { return { label: o.competitor, sub: o.retailer, value: o.price }; }));
    slot(box, 'chart').append(barChart(rows.map(function (r) {
        return { label: r.label, sub: r.sub, value: r.value };
    }), { format: function (v) { return money.format(v); }, valueName: 'Precio', label: 'Precio de ' + p.name + ' con nosotros y en el mercado' }));
    const bars = box.querySelectorAll('.bar-mark');
    bars[0].className = 'bar-mark series-3';
    for (let i = 2; i < bars.length; i++) bars[i].className = 'bar-mark series-2';

    slot(box, 'offers').append(...m.offers.map(function (o) {
        const item = fill(clone('tpl-market-offer'), {
            name: o.competitor + ' · ' + o.competitor_product,
            where: '(' + o.retailer + ')',
            price: money.format(o.price)
        });
        if (o.source_url) field(item, 'name').href = o.source_url; else field(item, 'name').removeAttribute('href');
        return item;
    }));
    target.replaceWith(box);
}

function showResult(view, found, code) {
    const p = found.product;
    const v = found.variant;
    const card = fill(clone('tpl-scan-result'), {
        category: p.category,
        name: p.name,
        variant: v.color + ' · ' + (v.size === 'UNI' ? 'Unitalla' : 'Talla ' + sizeLabel(v.size)),
        price: money.format(salePrice(p)),
        yourPrice: unitPrice(p) < salePrice(p) ? 'Tu precio: ' + money.format(unitPrice(p)) : (isOutlet(p) ? 'Outlet −' + p.outlet_percent + ' %' : ''),
        codes: 'SKU ' + v.sku + (v.barcode ? ' · EAN ' + v.barcode : '')
    });
    const href = '#/producto/' + p.id_product + '?color=' + encodeURIComponent(v.color_code);
    field(card, 'name').href = href;
    field(card, 'link').href = href;
    const art = slot(card, 'art');
    art.href = href;
    art.append(productArt(p, v.color_code));

    const online = stockAt(v, state.onlineStore);
    slot(card, 'stock').append(stockRow('Envío a domicilio', online), ...physicalStores().map(function (s) {
        return stockRow(s.store_name, stockAt(v, s.id_store));
    }));

    const add = $('[data-action="add"]', card);
    add.disabled = v.stock_total <= 0;
    if (v.stock_total <= 0) add.textContent = 'Agotado';
    add.addEventListener('click', function () {
        addToCart(v.id_variant, 1);
        openMiniCart(v.id_variant, 1);
    });

    slot(view, 'result').replaceChildren(card);
    marketSection(slot(card, 'market'), p);

    if (!history.some(function (h) { return h.variant.id_variant === v.id_variant; })) {
        history.unshift(found);
        history.splice(6);
    }
    drawHistory(view);
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (navigator.vibrate) navigator.vibrate(80);
    fill(view, { status: 'Código leído: ' + code });
}

function drawHistory(view) {
    show(slot(view, 'historyBox'), history.length > 0);
    slot(view, 'history').replaceChildren(...history.map(function (h) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'chip';
        button.textContent = h.product.name + ' · ' + sizeLabel(h.variant.size);
        button.addEventListener('click', function () { showResult(view, h, h.variant.barcode || h.variant.sku); });
        return button;
    }));
}

/* Código de barras o SKU exacto; si no, el producto cuyo nombre más se parece. */
function lookup(code) {
    const exact = findByCode(code);
    if (exact) return exact;
    const best = state.products.map(function (p) {
        return { p: p, score: matchScore(code, productText(p)) };
    }).filter(function (r) { return r.score > 0; }).sort(function (a, b) { return b.score - a.score; })[0];
    if (!best) return null;
    const variant = best.p.variants.find(function (v) { return v.stock_total > 0; }) || best.p.variants[0];
    return variant ? { product: best.p, variant: variant } : null;
}

function handleCode(view, code) {
    const found = lookup(code);
    if (!found) {
        fill(view, { status: 'No encontramos ningún producto JAM con “' + code + '”.' });
        field(view, 'status').className = 'scanner-status small bad-text';
        return false;
    }
    field(view, 'status').className = 'scanner-status small ok-text';
    showResult(view, found, code);
    return true;
}

export async function renderScanner(page) {
    setTitle('Escanear producto');
    const view = clone('tpl-scanner');
    const video = $('video', view);
    const cameraButton = $('[data-action="camera"]', view);
    const session = { stream: null, frame: null };
    page.replaceChildren(view);

    const detector = await createDetector();
    const secure = window.isSecureContext;
    if (!detector) {
        fill(view, { status: secure
            ? 'Este navegador no lee códigos de barras con la cámara (funciona en Chrome para Android). Escribe el código abajo.'
            : 'La cámara solo funciona en una dirección segura (https). Escribe el código abajo.' });
        cameraButton.disabled = true;
        $('.scanner-photo', view).hidden = true;
    }

    const scanFrame = async function () {
        if (!session.stream || !document.body.contains(video)) {
            stopCamera(session);
            return;
        }
        try {
            const codes = await detector.detect(video);
            if (codes.length && handleCode(view, codes[0].rawValue)) {
                stopCamera(session);
                view.classList.remove('camera-on');
                cameraButton.textContent = 'Escanear otro';
                return;
            }
        } catch (e) {
            /* cuadro sin código */
        }
        session.frame = requestAnimationFrame(function () { setTimeout(scanFrame, 200); });
    };

    cameraButton.addEventListener('click', async function () {
        if (session.stream) {
            stopCamera(session);
            view.classList.remove('camera-on');
            cameraButton.textContent = 'Activar cámara';
            return;
        }
        try {
            session.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        } catch (err) {
            toast('No se pudo abrir la cámara: revisa el permiso del navegador.', { error: true });
            return;
        }
        video.srcObject = session.stream;
        await video.play();
        view.classList.add('camera-on');
        cameraButton.textContent = 'Apagar cámara';
        fill(view, { status: 'Buscando un código de barras…' });
        field(view, 'status').className = 'scanner-status small';
        scanFrame();
    });

    $('.scanner-photo input', view).addEventListener('change', async function (e) {
        const file = e.target.files[0];
        if (!file || !detector) return;
        try {
            const image = await createImageBitmap(file);
            const codes = await detector.detect(image);
            if (!codes.length) {
                fill(view, { status: 'No se encontró un código de barras en la foto. Acércate y vuelve a intentarlo.' });
                return;
            }
            handleCode(view, codes[0].rawValue);
        } catch (err) {
            fill(view, { status: 'No se pudo leer la foto.' });
        }
        e.target.value = '';
    });

    $('.scanner-manual', view).addEventListener('submit', function (e) {
        e.preventDefault();
        const code = e.target.code.value.trim();
        if (code) handleCode(view, code);
    });

    window.addEventListener('hashchange', function stop() {
        stopCamera(session);
        window.removeEventListener('hashchange', stop);
    });
    drawHistory(view);
}
