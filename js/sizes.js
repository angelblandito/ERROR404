/* Guía de tallas: tabla de medidas y recomendación según las medidas del cliente. */
import { $, clone, field, slot, show } from './ui.js';

/* Medidas en centímetros (rango inclusivo). */
const APPAREL = [
    { size: 'XS', chest: [80, 85], waist: [64, 69] },
    { size: 'S', chest: [86, 92], waist: [70, 76] },
    { size: 'M', chest: [93, 100], waist: [77, 84] },
    { size: 'L', chest: [101, 108], waist: [85, 92] },
    { size: 'XL', chest: [109, 116], waist: [93, 100] },
    { size: 'XXL', chest: [117, 124], waist: [101, 108] }
];

/* Talla mexicana de calzado: equivale al largo del pie en centímetros. */
const FOOTWEAR = [
    { size: '25', foot: [24.1, 25] },
    { size: '26', foot: [25.1, 26] },
    { size: '27', foot: [26.1, 27] },
    { size: '28', foot: [27.1, 28] }
];

function inRange(value, range) {
    return value >= range[0] && value <= range[1];
}

function nearestIndex(list, key, value) {
    let best = 0;
    list.forEach(function (row, i) {
        const center = (row[key][0] + row[key][1]) / 2;
        const bestCenter = (list[best][key][0] + list[best][key][1]) / 2;
        if (Math.abs(center - value) < Math.abs(bestCenter - value)) best = i;
    });
    return best;
}

/* Recomienda la talla de ropa: si pecho y cintura no coinciden, la más grande. */
function apparelSize(chest, waist) {
    const picks = [];
    if (chest) picks.push(nearestIndex(APPAREL, 'chest', chest));
    if (waist) picks.push(nearestIndex(APPAREL, 'waist', waist));
    if (!picks.length) return null;
    const row = APPAREL[Math.max.apply(null, picks)];
    const exact = (!chest || inRange(chest, row.chest) || chest < APPAREL[0].chest[0]) &&
        (!waist || inRange(waist, row.waist) || waist < APPAREL[0].waist[0]);
    return { size: row.size, exact: exact };
}

function footwearSize(foot) {
    if (!foot) return null;
    const row = FOOTWEAR[nearestIndex(FOOTWEAR, 'foot', foot)];
    return { size: row.size, exact: inRange(foot, row.foot) };
}

function cell(tag, text) {
    const el = document.createElement(tag);
    el.textContent = text;
    return el;
}

function fillTable(dialog, isShoe) {
    const head = isShoe ? ['Talla MX', 'Largo del pie'] : ['Talla', 'Pecho', 'Cintura'];
    slot(dialog, 'head').replaceChildren(...head.map(function (h) { return cell('th', h); }));
    slot(dialog, 'rows').replaceChildren(...(isShoe ? FOOTWEAR : APPAREL).map(function (r) {
        const row = document.createElement('tr');
        row.dataset.size = r.size;
        row.append(cell('td', r.size));
        if (isShoe) {
            row.append(cell('td', r.foot[0] + '–' + r.foot[1] + ' cm'));
        } else {
            row.append(cell('td', r.chest[0] + '–' + r.chest[1] + ' cm'), cell('td', r.waist[0] + '–' + r.waist[1] + ' cm'));
        }
        return row;
    }));
}

/* Abre la guía. available: tallas con existencias en el color elegido.
   onPick(size) selecciona la talla recomendada en la página del producto. */
export function openSizeFinder(product, available, onPick) {
    const isShoe = product.department === 'footwear';
    const dialog = clone('tpl-size-finder');
    show(field(dialog, 'apparel'), !isShoe);
    show(field(dialog, 'footwear'), isShoe);
    fillTable(dialog, isShoe);
    const form = $('form', dialog);
    const result = field(dialog, 'result');
    const useButton = $('[data-action="use-size"]', dialog);
    let pick = null;

    const update = function () {
        const rec = isShoe
            ? footwearSize(Number(form.foot.value))
            : apparelSize(Number(form.chest.value), Number(form.waist.value));
        pick = rec;
        dialog.querySelectorAll('tr[data-size]').forEach(function (tr) {
            tr.classList.toggle('active', Boolean(rec) && tr.dataset.size === rec.size);
        });
        if (!rec) {
            result.textContent = 'Escribe tus medidas para ver la talla recomendada.';
            useButton.hidden = true;
            return;
        }
        const has = available.indexOf(rec.size) >= 0;
        result.textContent = 'Te recomendamos la talla ' + rec.size + (rec.exact ? '.' : ' (tus medidas quedan entre dos tallas).')
            + (has ? '' : ' No hay existencias en este color.');
        useButton.hidden = !has;
        useButton.textContent = 'Elegir talla ' + rec.size;
    };

    form.addEventListener('input', update);
    useButton.addEventListener('click', function () {
        if (pick) onPick(pick.size);
        dialog.close();
    });
    dialog.addEventListener('close', function () { dialog.remove(); });
    document.body.append(dialog);
    update();
    dialog.showModal();
}
