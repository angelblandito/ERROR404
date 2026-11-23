/*
 * Animación de fondo según la temporada (columna season_theme.effect).
 * Se dibuja en un <canvas> que no recibe clics, así que no estorba al comprar.
 * Las partículas se apartan del cursor y el viento cambia al hacer scroll.
 */
import { $ } from './ui.js';

const EFFECTS = {
    snow: { kinds: ['snow'], density: 1 },
    festive: { kinds: ['snow', 'snow', 'snow', 'light'], density: 1 },
    leaves: { kinds: ['leaf'], density: 0.45 },
    petals: { kinds: ['petal'], density: 0.6 },
    sun: { kinds: ['bubble'], density: 0.45 },
    confetti: { kinds: ['confetti'], density: 0.55 },
    sparks: { kinds: ['spark', 'spark', 'confetti'], density: 0.7 }
};

const PALETTES = {
    leaf: ['#C2410C', '#EA580C', '#D97706', '#B45309', '#9A3412', '#CA8A04'],
    petal: ['#FFC2D1', '#FFB3C7', '#FF8FAB', '#FFE5EC'],
    light: ['#E63946', '#FFD166', '#06D6A0', '#4CC9F0']
};

const RISING = { bubble: true, spark: true };
const STORAGE_KEY = 'jam_fx';
const POINTER_RADIUS = 90;

const fx = {
    canvas: null,
    ctx: null,
    width: 0,
    height: 0,
    effect: 'none',
    colors: [],
    particles: [],
    enabled: true,
    frame: 0,
    last: 0,
    wind: 0,
    gust: 0,
    scrollY: 0,
    pointer: { x: -9999, y: -9999 }
};

function random(min, max) {
    return min + Math.random() * (max - min);
}

function pick(list) {
    return list[Math.floor(Math.random() * list.length)];
}

function createParticle(kind, anywhere) {
    const rising = Boolean(RISING[kind]);
    const p = {
        kind: kind,
        x: random(0, fx.width),
        y: anywhere ? random(0, fx.height) : (rising ? fx.height + 20 : -20),
        rotation: random(0, Math.PI * 2),
        spin: random(-0.03, 0.03),
        phase: random(0, Math.PI * 2),
        sway: random(0.4, 1.2),
        pushX: 0,
        pushY: 0
    };

    if (kind === 'snow') {
        p.size = random(1.5, 4.5);
        p.speed = random(0.35, 0.6) * p.size;
    } else if (kind === 'light') {
        p.size = random(3, 5);
        p.speed = random(0.4, 0.9);
        p.color = pick(PALETTES.light);
    } else if (kind === 'leaf') {
        p.size = random(9, 17);
        p.speed = random(0.7, 1.4);
        p.sway = random(1, 2);
        p.color = pick(PALETTES.leaf);
    } else if (kind === 'petal') {
        p.size = random(5, 9);
        p.speed = random(0.5, 1.1);
        p.sway = random(0.8, 1.6);
        p.color = pick(PALETTES.petal);
    } else if (kind === 'bubble') {
        p.size = random(4, 14);
        p.speed = -random(0.3, 0.9);
    } else if (kind === 'confetti') {
        p.size = random(5, 9);
        p.speed = random(1, 2.2);
        p.spin = random(-0.08, 0.08);
        p.color = pick(fx.colors);
    } else {
        p.size = random(1.2, 2.8);
        p.speed = -random(0.4, 1.2);
        p.color = fx.colors[0];
    }
    return p;
}

function targetCount() {
    const config = EFFECTS[fx.effect];
    if (!config) return 0;
    const count = Math.round(fx.width * fx.height / 16000 * config.density);
    return Math.max(12, Math.min(110, count));
}

function fillParticles() {
    const config = EFFECTS[fx.effect];
    const total = targetCount();
    if (!config) {
        fx.particles = [];
        return;
    }
    fx.particles = fx.particles.slice(0, total);
    while (fx.particles.length < total) {
        fx.particles.push(createParticle(pick(config.kinds), true));
    }
}

function resize() {
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    fx.width = window.innerWidth;
    fx.height = window.innerHeight;
    fx.canvas.width = Math.round(fx.width * ratio);
    fx.canvas.height = Math.round(fx.height * ratio);
    fx.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    fillParticles();
}

/* ---------- Dibujo de cada tipo de partícula ---------- */
function drawSnow(ctx, p) {
    ctx.beginPath();
    ctx.arc(0, 0, p.size, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(255, 255, 255, .95)';
    ctx.shadowColor = 'rgba(20, 50, 100, .45)';
    ctx.shadowBlur = 4;
    ctx.fill();
}

function drawGlow(ctx, p, alpha) {
    const glow = ctx.createRadialGradient(0, 0, 0, 0, 0, p.size * 3);
    glow.addColorStop(0, p.color);
    glow.addColorStop(1, 'rgba(255, 255, 255, 0)');
    ctx.globalAlpha = alpha;
    ctx.fillStyle = glow;
    ctx.beginPath();
    ctx.arc(0, 0, p.size * 3, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = p.color;
    ctx.beginPath();
    ctx.arc(0, 0, p.size * 0.8, 0, Math.PI * 2);
    ctx.fill();
}

function drawLeaf(ctx, p) {
    const s = p.size;
    ctx.scale(1, Math.cos(p.phase * 0.7));
    ctx.beginPath();
    ctx.moveTo(0, -s);
    ctx.quadraticCurveTo(s * 0.8, -s * 0.2, 0, s);
    ctx.quadraticCurveTo(-s * 0.8, -s * 0.2, 0, -s);
    ctx.fillStyle = p.color;
    ctx.fill();
    ctx.beginPath();
    ctx.moveTo(0, -s * 0.85);
    ctx.lineTo(0, s * 1.25);
    ctx.lineWidth = 0.8;
    ctx.strokeStyle = 'rgba(60, 25, 10, .45)';
    ctx.stroke();
}

function drawPetal(ctx, p) {
    const s = p.size;
    ctx.scale(1, Math.cos(p.phase));
    ctx.beginPath();
    ctx.moveTo(0, -s);
    ctx.bezierCurveTo(s, -s, s, s * 0.6, 0, s);
    ctx.bezierCurveTo(-s, s * 0.6, -s, -s, 0, -s);
    ctx.fillStyle = p.color;
    ctx.fill();
    ctx.lineWidth = 0.5;
    ctx.strokeStyle = 'rgba(160, 60, 90, .3)';
    ctx.stroke();
}

function drawBubble(ctx, p) {
    ctx.beginPath();
    ctx.arc(0, 0, p.size, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(255, 210, 63, .12)';
    ctx.fill();
    ctx.lineWidth = 1.2;
    ctx.strokeStyle = 'rgba(230, 170, 20, .55)';
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(-p.size * 0.35, -p.size * 0.35, p.size * 0.22, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(255, 255, 255, .85)';
    ctx.fill();
}

function drawConfetti(ctx, p) {
    ctx.scale(1, Math.cos(p.phase * 1.5));
    ctx.fillStyle = p.color;
    ctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2);
}

function draw(ctx, p) {
    ctx.save();
    ctx.translate(p.x, p.y);
    if (p.kind === 'snow') {
        drawSnow(ctx, p);
    } else if (p.kind === 'light' || p.kind === 'spark') {
        drawGlow(ctx, p, 0.55 + 0.45 * Math.sin(p.phase * 3));
    } else if (p.kind === 'bubble') {
        drawBubble(ctx, p);
    } else {
        ctx.rotate(p.rotation);
        if (p.kind === 'leaf') drawLeaf(ctx, p);
        if (p.kind === 'petal') drawPetal(ctx, p);
        if (p.kind === 'confetti') drawConfetti(ctx, p);
    }
    ctx.restore();
}

/* ---------- Movimiento ---------- */
function move(p, step) {
    p.phase += 0.02 * step;
    p.rotation += p.spin * step;

    const dx = p.x - fx.pointer.x;
    const dy = p.y - fx.pointer.y;
    const distance = Math.sqrt(dx * dx + dy * dy);
    if (distance < POINTER_RADIUS && distance > 0) {
        const force = (POINTER_RADIUS - distance) / POINTER_RADIUS * 2.5;
        p.pushX += dx / distance * force;
        p.pushY += dy / distance * force;
    }
    p.pushX *= 0.92;
    p.pushY *= 0.92;

    const drift = Math.sin(p.phase) * p.sway + fx.wind * (p.kind === 'leaf' ? 1.6 : 1);
    p.x += (drift + p.pushX) * step;
    p.y += (p.speed + p.pushY) * step;

    if (p.x < -30) p.x = fx.width + 30;
    if (p.x > fx.width + 30) p.x = -30;

    const gone = RISING[p.kind] ? p.y < -30 : p.y > fx.height + 30;
    if (gone) Object.assign(p, createParticle(p.kind, false));
}

function tick(time) {
    const step = Math.min(3, (time - (fx.last || time)) / 16.67 || 1);
    fx.last = time;
    fx.gust *= 0.95;
    fx.wind = 0.25 + fx.gust;

    fx.ctx.clearRect(0, 0, fx.width, fx.height);
    fx.particles.forEach(function (p) {
        move(p, step);
        draw(fx.ctx, p);
    });
    fx.frame = requestAnimationFrame(tick);
}

function stop() {
    cancelAnimationFrame(fx.frame);
    fx.frame = 0;
    fx.last = 0;
    if (fx.ctx) fx.ctx.clearRect(0, 0, fx.width, fx.height);
}

function start() {
    stop();
    const active = fx.enabled && EFFECTS[fx.effect] && !document.hidden;
    fx.canvas.hidden = !active;
    if (active) fx.frame = requestAnimationFrame(tick);
}

/* ---------- Preferencia del usuario ---------- */
function savedPreference() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) return saved === 'on';
    } catch (e) {
        /* sin localStorage */
    }
    return !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function savePreference(on) {
    try {
        localStorage.setItem(STORAGE_KEY, on ? 'on' : 'off');
    } catch (e) {
        /* sin localStorage */
    }
}

export function setEffect(effect, colors) {
    fx.effect = EFFECTS[effect] ? effect : 'none';
    fx.colors = colors.filter(Boolean);
    fx.particles = [];
    fillParticles();
    $('#fxSwitch').hidden = fx.effect === 'none';
    start();
}

export function initEffects() {
    fx.canvas = $('#seasonFx');
    fx.ctx = fx.canvas.getContext('2d');
    fx.enabled = savedPreference();
    fx.scrollY = window.scrollY;

    const toggle = $('#fxToggle');
    toggle.checked = fx.enabled;
    toggle.addEventListener('change', function () {
        fx.enabled = toggle.checked;
        savePreference(fx.enabled);
        start();
    });

    document.addEventListener('jam:theme', function (e) {
        const t = e.detail;
        setEffect(t.effect, [t.color_accent, t.color_secondary, t.color_primary, '#FFD166']);
    });

    window.addEventListener('resize', resize);
    window.addEventListener('scroll', function () {
        const delta = window.scrollY - fx.scrollY;
        fx.scrollY = window.scrollY;
        fx.gust = Math.max(-4, Math.min(4, fx.gust + delta * 0.02));
    }, { passive: true });
    window.addEventListener('pointermove', function (e) {
        fx.pointer.x = e.clientX;
        fx.pointer.y = e.clientY;
    }, { passive: true });
    document.addEventListener('pointerleave', function () {
        fx.pointer.x = -9999;
        fx.pointer.y = -9999;
    });
    document.addEventListener('visibilitychange', start);

    resize();
}
