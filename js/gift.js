/* Empaque especial JAM para regalo: ocasiones, colores y la elección del cliente. */
import { $, clone, fill, money, slot } from './ui.js';
import { state } from './store.js';

export const OCCASIONS = { birthday: 'Cumpleaños', friendship: 'Amistad', christmas: 'Navidad' };

export const WRAP_COLORS = {
    red: 'Rojo', green: 'Verde', blue: 'Azul', pink: 'Rosa', gold: 'Dorado', silver: 'Plateado', purple: 'Morado', black: 'Negro'
};

/* Color sugerido al elegir la ocasión. */
const OCCASION_COLOR = { birthday: 'blue', friendship: 'purple', christmas: 'red' };

const KEY = 'jam_gift';

export function giftCost() {
    return state.giftWrap ? Number(state.giftWrap.cost) : 30;
}

export function suggestedColor(occasion) {
    return OCCASION_COLOR[occasion] || 'red';
}

/* La elección se guarda mientras dura la visita, para no preguntarla dos veces. */
export function savedGift() {
    try {
        return JSON.parse(sessionStorage.getItem(KEY));
    } catch (e) {
        return null;
    }
}

export function saveGift(gift) {
    try {
        if (gift) sessionStorage.setItem(KEY, JSON.stringify(gift));
        else sessionStorage.removeItem(KEY);
    } catch (e) {
        /* sin sessionStorage */
    }
}

export function giftArt(color) {
    const art = clone('tpl-gift-art');
    art.classList.add('wrap-' + (WRAP_COLORS[color] ? color : 'red'));
    art.querySelector('svg').setAttribute('aria-label', 'Regalo envuelto en papel ' + (WRAP_COLORS[color] || '').toLowerCase());
    return art;
}

const OCCASION_PHRASE = { birthday: 'cumpleaños', friendship: 'amistad', christmas: 'Navidad' };

export function giftSummary(occasion, color, recipient) {
    return 'Regalo de ' + (OCCASION_PHRASE[occasion] || occasion) + ' · papel ' + (WRAP_COLORS[color] || color).toLowerCase()
        + (recipient ? ' · para ' + recipient : '');
}

/* Pregunta al pasar del carrito al pago si quiere el empaque especial. */
export function offerGiftWrap(onDone) {
    const previous = savedGift();
    if (previous) {
        onDone();
        return;
    }
    const dialog = fill(clone('tpl-gift-offer'), { cost: money.format(giftCost()) });
    slot(dialog, 'preview').replaceWith(giftArt('red'));
    dialog.addEventListener('close', function () {
        saveGift({ enabled: dialog.returnValue === 'gift' });
        dialog.remove();
        onDone();
    });
    document.body.append(dialog);
    dialog.showModal();
    $('button[value="gift"]', dialog).focus();
}
