/* Contacto y emergencias: teléfonos, WhatsApp y correo de la tienda en línea y de cada sucursal. */
import { clone, field, fill, show, slot, setTitle } from '../ui.js';
import { state } from '../store.js';

/* "6641110001" -> "664 111 0001" */
export function formatPhone(phone) {
    const digits = String(phone || '').replace(/\D/g, '');
    return digits.length === 10 ? digits.slice(0, 3) + ' ' + digits.slice(3, 6) + ' ' + digits.slice(6) : digits;
}

export function telLink(phone) {
    return 'tel:+52' + String(phone || '').replace(/\D/g, '');
}

export function whatsappLink(phone, text) {
    return 'https://wa.me/52' + String(phone || '').replace(/\D/g, '') + (text ? '?text=' + encodeURIComponent(text) : '');
}

function link(el, href, text) {
    if (!href) {
        el.remove();
        return;
    }
    el.href = href;
    if (text) el.textContent = text;
}

function storeContact(s) {
    const card = fill(clone('tpl-contact-store'), {
        name: s.store_name,
        location: s.is_online ? 'Pedidos en línea y envíos a todo México' : [s.city, s.state].filter(Boolean).join(', '),
        hours: s.opening_hours ? 'Horario: ' + s.opening_hours : ''
    });
    show(field(card, 'hours'), !s.is_online && Boolean(s.opening_hours));
    link(field(card, 'call'), s.contact_phone ? telLink(s.contact_phone) : '', 'Llamar · ' + formatPhone(s.contact_phone));
    link(field(card, 'whatsapp'), s.whatsapp ? whatsappLink(s.whatsapp, 'Hola ' + s.store_name + ', tengo una pregunta.') : '');
    link(field(card, 'email'), s.email ? 'mailto:' + s.email : '', s.email || '');
    return card;
}

export function renderContact(page) {
    setTitle('Contacto y emergencias');
    const view = clone('tpl-contact');
    const online = state.stores.find(function (s) { return s.is_online; }) || state.stores[0];

    if (online) {
        link(field(view, 'urgentLine'), telLink(online.contact_phone), 'Línea urgente · ' + formatPhone(online.contact_phone));
        link(field(view, 'mainPhone'), telLink(online.contact_phone));
        field(view, 'mainPhoneText').textContent = formatPhone(online.contact_phone);
        link(field(view, 'mainWhatsapp'), whatsappLink(online.whatsapp || online.contact_phone, 'Hola JAM Sport, necesito ayuda con mi pedido.'));
        field(view, 'mainWhatsappText').textContent = formatPhone(online.whatsapp || online.contact_phone);
        if (online.email) {
            link(field(view, 'mainEmail'), 'mailto:' + online.email);
            field(view, 'mainEmailText').textContent = online.email;
        } else {
            field(view, 'mainEmail').remove();
        }
    }
    slot(view, 'stores').append(...state.stores.map(storeContact));
    page.replaceChildren(view);
}
