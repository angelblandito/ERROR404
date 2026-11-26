/* Cómo ser cliente Prioritario: requisitos, avance de la cuenta Estándar y ascenso. */
import { $, clone, field, fill, show, slot, money, message, toast, formatDate, loading, setTitle } from '../ui.js';
import { benefitItems } from '../components.js';
import { account, priorityStatus, state, tierInfo, upgradeToPriority } from '../store.js';

function meter(label, value, percent, note, done) {
    const el = fill(clone('tpl-priority-meter'), { label: label, value: value, note: note });
    const bar = $('.meter-bar', el);
    bar.setAttribute('aria-valuenow', String(percent));
    bar.setAttribute('aria-label', label);
    field(el, 'fill').style.width = Math.max(2, percent) + '%';
    el.classList.toggle('done', done);
    return el;
}

/* Dos barras: compras y antigüedad. compact = versión corta para Mi cuenta. */
export function priorityMeters(st, compact) {
    const wrap = document.createElement('div');
    wrap.className = 'meters' + (compact ? ' compact' : '');
    wrap.append(
        meter('Compras',
            money.format(st.spent) + ' de ' + money.format(st.required_spent),
            st.spent_percent,
            st.meets_spent ? '✓ Cumplido' : 'Te faltan ' + money.format(st.missing_spent) + ' en compras.',
            st.meets_spent),
        meter('Antigüedad',
            st.days + ' de ' + (st.required_days + 1) + ' días',
            st.days_percent,
            st.meets_days ? '✓ Cumplido' : 'Cumples un año el ' + formatDate(st.days_ready_on) + ' (faltan ' + st.missing_days + ' días).',
            st.meets_days)
    );
    return wrap;
}

function progressBox(st, onUpgrade) {
    const box = fill(clone('tpl-priority-progress'), {
        title: st.eligible ? '¡Ya cumples los requisitos!' : 'Tu avance, ' + (state.customer ? state.customer.first_name : ''),
        intro: 'Cliente desde el ' + formatDate(st.customer_since) + '.',
        hint: st.eligible
            ? 'El cambio es inmediato y te llegará un correo de confirmación.'
            : 'Cuando cumplas los dos requisitos aparecerá aquí el botón para subir tu cuenta.'
    });
    slot(box, 'meters').replaceWith(priorityMeters(st, false));
    const button = $('[data-action="upgrade"]', box);
    show(button, st.eligible);
    button.addEventListener('click', async function () {
        button.disabled = true;
        try {
            await upgradeToPriority();
            toast('¡Bienvenido a Prioritario! Ya tienes tus beneficios.');
            onUpgrade();
        } catch (err) {
            toast(err.message, { error: true });
            button.disabled = false;
        }
    });
    return box;
}

function alreadyBox(acc) {
    const box = document.createElement('div');
    box.className = 'priority-box gold-card';
    const title = document.createElement('h2');
    title.textContent = acc.tier === 'admin' ? 'Tu cuenta es de Administrador' : '¡Ya eres cliente Prioritario!';
    const text = document.createElement('p');
    text.textContent = acc.tier === 'admin'
        ? 'Las cuentas de administrador ya tienen todos los beneficios de Prioritario.'
        : 'Disfruta tu descuento, envío prioritario, compras de mayoreo y soporte en minutos.';
    const link = document.createElement('a');
    link.className = 'btn btn-dark';
    link.href = '#/mercado';
    link.textContent = 'Ir al comparador de mercado';
    box.append(title, text, link);
    return box;
}

export async function renderPriority(page) {
    setTitle('Cómo ser Prioritario');
    const view = clone('tpl-priority');
    const standard = tierInfo('standard');
    const priority = tierInfo('priority');
    if (standard) slot(view, 'standard').append(...benefitItems(standard));
    if (priority) slot(view, 'priority').append(...benefitItems(priority));
    const status = slot(view, 'status');
    page.replaceChildren(view);

    if (!state.customer) {
        status.replaceChildren(clone('tpl-priority-guest'));
        return;
    }
    const acc = account();
    if (acc.tier !== 'standard') {
        status.replaceChildren(alreadyBox(acc));
        return;
    }
    status.replaceChildren(loading('Calculando tu avance…'));
    try {
        const st = await priorityStatus();
        status.replaceChildren(progressBox(st, function () { renderPriority(page); }));
    } catch (err) {
        status.replaceChildren(message(err.message, 'bad-text'));
    }
}
