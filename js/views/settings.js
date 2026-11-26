/* Configuración de la cuenta: datos personales, preferencias y contraseña. */
import { $, clone, field, fill, formatDate, setTitle, toast } from '../ui.js';
import { account, changePassword, logout, state, updateProfile } from '../store.js';
import { authPanel } from './account.js';

function onSubmit(form, action) {
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            await action(Object.fromEntries(new FormData(form)));
        } catch (err) {
            toast(err.message, { error: true });
        }
        button.disabled = false;
    });
}

export function renderSettings(page) {
    setTitle('Configuración');

    if (!state.customer) {
        page.replaceChildren(authPanel('Configuración', 'Inicia sesión para cambiar tus datos y tu contraseña.', function () {
            renderSettings(page);
        }));
        return;
    }

    const c = state.customer;
    const view = fill(clone('tpl-settings'), {
        tier: account().name,
        since: formatDate(c.customer_since)
    });
    field(view, 'email').value = c.email;
    field(view, 'birth').value = formatDate(c.birth_date);

    const profile = $('[data-form="profile"]', view);
    profile.first_name.value = c.first_name || '';
    profile.last_name_paternal.value = c.last_name_paternal || '';
    profile.last_name_maternal.value = c.last_name_maternal || '';
    profile.phone.value = c.phone || '';
    profile.accepts_marketing.checked = Boolean(c.accepts_marketing);

    onSubmit(profile, async function (d) {
        d.accepts_marketing = d.accepts_marketing === '1';
        await updateProfile(d);
        toast('Tus datos se guardaron');
    });

    const password = $('[data-form="password"]', view);
    onSubmit(password, async function (d) {
        if (d.new_password !== d.confirm_password) {
            throw new Error('Las dos contraseñas nuevas no coinciden.');
        }
        await changePassword(d.current_password, d.new_password);
        toast('Tu contraseña cambió. Inicia sesión con la nueva.');
        window.location.hash = '#/cuenta';
    });

    $('[data-action="logout"]', view).addEventListener('click', async function () {
        await logout();
        toast('Cerraste sesión');
        window.location.hash = '#/';
    });

    page.replaceChildren(view);
}
