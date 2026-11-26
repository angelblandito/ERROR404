/* Inicio: banner de temporada, departamentos, carruseles y cupones. */
import { clone, field, fill, slot, money, setTitle } from '../ui.js';
import { hasPhoto, productArt } from '../illustrations.js';
import { announcementsSection, productCard, productRail } from '../components.js';
import { activePromotions, colorsOf, isPriority, loadAnnouncements, newestIds, outletProducts, recentProducts, seasonProducts, shopProducts, state } from '../store.js';
import { campaignEnd, currentTheme, seasonByDate, themeName } from '../theme.js';

const SEASON_TABS = ['spring', 'summer', 'autumn', 'winter', 'back_to_school', 'christmas'];

let countdownTimer = null;

/* Cuenta regresiva hasta el fin de la campaña (Black Friday, Navidad…). */
function startCountdown(root, theme) {
    clearInterval(countdownTimer);
    const box = field(root, 'countdown');
    const end = campaignEnd(theme);
    const tick = function (first) {
        if (first !== true && !document.body.contains(box)) {
            clearInterval(countdownTimer);
            return;
        }
        const left = end ? end.getTime() - Date.now() : 0;
        box.hidden = left <= 0;
        if (left <= 0) return;
        const minutes = Math.floor(left / 60000);
        fill(root, {
            cdDays: Math.floor(minutes / 1440),
            cdHours: String(Math.floor(minutes / 60) % 24).padStart(2, '0'),
            cdMinutes: String(minutes % 60).padStart(2, '0')
        });
    };
    tick(true);
    if (end) countdownTimer = setInterval(tick, 30000);
}

function showTheme(root, theme) {
    fill(root, { season: theme.name, heroTitle: theme.hero_title, heroText: theme.hero_text });
    startCountdown(root, theme);
}

/* Lo más vendido del departamento que tenga foto; si ninguno tiene, el dibujo de siempre. */
function departmentArt(department, fallback) {
    const list = shopProducts().filter(function (p) { return p.department === department && hasPhoto(p); })
        .sort(function (a, b) { return b.sold - a.sold; });
    if (!list.length) return productArt(fallback[0], fallback[1]);
    const colors = colorsOf(list[0]);
    return productArt(list[0], colors[0] ? colors[0].color_code : 'black');
}

function bySeasonOrder(a, b) {
    return ((b.stock_total > 0) - (a.stock_total > 0)) || (b.sold - a.sold) || (b.id_product - a.id_product);
}

/* Pestañas Primavera, Verano, Otoño, Invierno, Regreso a clases y Navidad. */
function seasonCollection() {
    const section = clone('tpl-season-collection');
    const codes = SEASON_TABS.filter(function (code) { return seasonProducts(code).length > 0; });
    if (!codes.length) {
        section.hidden = true;
        return section;
    }
    const tabs = slot(section, 'tabs');
    const track = slot(section, 'items');

    const pick = function (code) {
        Array.from(tabs.children).forEach(function (button) {
            button.setAttribute('aria-pressed', String(button.dataset.season === code));
        });
        const list = seasonProducts(code).sort(bySeasonOrder);
        track.replaceChildren(...list.slice(0, 12).map(productCard));
        field(section, 'link').href = '#/tienda?temporada=' + code;
        fill(section, { note: list.length + (list.length === 1 ? ' producto' : ' productos') + ' de ' + themeName(code) });
    };

    codes.forEach(function (code) {
        const button = clone('tpl-season-tab');
        button.dataset.season = code;
        button.textContent = themeName(code);
        button.addEventListener('click', function () { pick(code); });
        tabs.append(button);
    });

    const today = codes.indexOf(currentTheme.code) >= 0 ? currentTheme.code : seasonByDate();
    pick(codes.indexOf(today) >= 0 ? today : codes[0]);
    return section;
}

function couponTile(promo) {
    const pct = Number(promo.discount_percent);
    return fill(clone('tpl-coupon'), {
        pct: pct > 0 ? pct + '%' : 'Envío',
        description: promo.description || promo.code,
        code: promo.code
    });
}

export async function renderHome(view) {
    setTitle('');
    const home = clone('tpl-home');

    const bestSellers = shopProducts().sort(function (a, b) {
        return (b.sold - a.sold) || (b.rating - a.rating);
    }).slice(0, 8);
    const newest = newestIds(8).map(function (id) { return state.byId.get(id); });
    const topRated = shopProducts().filter(function (p) { return p.reviews > 0; })
        .sort(function (a, b) { return (b.rating - a.rating) || (b.reviews - a.reviews); }).slice(0, 8);

    showTheme(home, currentTheme);
    fill(home, { freeFrom: money.format(state.shipping.free_from) });

    slot(home, 'heroArt').append(productArt({ name: 'Tenis', category_code: 'footwear' }, 'white', 'hero-shoe'));
    slot(home, 'tileFootwear').replaceWith(departmentArt('footwear', [{ name: 'Tenis', category_code: 'footwear' }, 'black']));
    slot(home, 'tileApparel').replaceWith(departmentArt('apparel', [{ name: 'Sudadera', category_code: 'hoodies' }, 'gray']));
    slot(home, 'tileAccessories').replaceWith(departmentArt('accessories', [{ name: 'Mochila', category_code: 'accessories' }, 'navy']));

    slot(home, 'recent').replaceWith(productRail('Vistos recientemente', recentProducts().slice(0, 8)));
    const outlet = outletProducts();
    const outletPercent = Math.max.apply(null, outlet.map(function (p) { return p.outlet_percent; }).concat([0]));
    fill(home, { outletPercent: outletPercent + ' %' });
    slot(home, 'outletArt').replaceWith(productArt({ name: 'Tenis', category_code: 'footwear' }, 'red'));
    field(home, 'outletBanner').hidden = !outlet.length;
    slot(home, 'bestSellers').replaceWith(productRail('Lo más vendido', bestSellers, { href: '#/tienda?orden=vendidos', label: 'Ver todo' }));
    slot(home, 'seasons').replaceWith(seasonCollection());
    slot(home, 'newest').replaceWith(productRail('Novedades', newest, { href: '#/tienda?orden=nuevo', label: 'Ver novedades' }));
    const deals = outlet.slice().sort(function (a, b) {
        return (b.outlet_percent - a.outlet_percent) || ((b.stock_total > 0) - (a.stock_total > 0)) || (b.sold - a.sold);
    }).slice(0, 8);
    slot(home, 'deals').replaceWith(productRail('Ofertas del outlet', deals, { href: '#/outlet', label: 'Ver todas las ofertas' }));
    slot(home, 'topRated').replaceWith(productRail('Mejor calificados', topRated, { href: '#/tienda?orden=calificacion', label: 'Ver todo' }));

    view.replaceChildren(home);

    const onTheme = function (e) {
        if (!document.body.contains(home)) {
            document.removeEventListener('jam:theme', onTheme);
            return;
        }
        showTheme(home, e.detail);
    };
    document.addEventListener('jam:theme', onTheme);

    loadAnnouncements().then(function (list) {
        slot(home, 'announcements').replaceWith(announcementsSection(list, isPriority() ? 'Para nuestros clientes Prioritarios' : 'Avisos'));
    });

    const promos = await activePromotions();
    const strip = slot(home, 'coupons');
    strip.append(...promos.slice(0, 3).map(couponTile));
    strip.hidden = !promos.length;
}
