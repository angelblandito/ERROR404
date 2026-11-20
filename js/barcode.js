/* Códigos de barras EAN-13: dígito verificador y dibujo en SVG para las etiquetas. */

const L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
const G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
const R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
/* Paridad de los primeros seis dígitos según el primer dígito del código. */
const PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

export function ean13CheckDigit(first12) {
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += Number(first12[i]) * (i % 2 === 0 ? 1 : 3);
    }
    return (10 - sum % 10) % 10;
}

export function isEan13(code) {
    return /^\d{13}$/.test(code) && ean13CheckDigit(code.slice(0, 12)) === Number(code[12]);
}

/* Secuencia de 95 módulos (1 = barra) del código. */
function modules(code) {
    const parity = PARITY[Number(code[0])];
    let bits = '101';
    for (let i = 1; i <= 6; i++) {
        bits += (parity[i - 1] === 'L' ? L : G)[Number(code[i])];
    }
    bits += '01010';
    for (let i = 7; i <= 12; i++) {
        bits += R[Number(code[i])];
    }
    return bits + '101';
}

const SVG_NS = 'http://www.w3.org/2000/svg';

function svgEl(name, attrs) {
    const el = document.createElementNS(SVG_NS, name);
    Object.keys(attrs).forEach(function (k) { el.setAttribute(k, attrs[k]); });
    return el;
}

export function ean13Svg(code) {
    const bits = modules(code);
    const quiet = 11;
    const width = bits.length + quiet * 2;
    const svg = svgEl('svg', { viewBox: '0 0 ' + width + ' 60', class: 'ean13', role: 'img', 'aria-label': 'Código de barras ' + code });
    svg.append(svgEl('rect', { x: 0, y: 0, width: width, height: 60, fill: '#fff' }));
    const guards = [0, 1, 2, 45, 46, 47, 48, 49, 92, 93, 94];
    let path = '';
    for (let i = 0; i < bits.length; i++) {
        if (bits[i] === '1') {
            const tall = guards.indexOf(i) >= 0;
            path += 'M' + (quiet + i) + ' 2h1v' + (tall ? 50 : 45) + 'h-1z';
        }
    }
    svg.append(svgEl('path', { d: path, fill: '#000' }));
    const digits = [[code[0], 5], [code.slice(1, 7), quiet + 3 + 21], [code.slice(7), quiet + 50 + 21]];
    digits.forEach(function (d) {
        const text = svgEl('text', { x: d[1], y: 58, 'text-anchor': 'middle', 'font-size': 8, 'font-family': 'monospace', 'letter-spacing': 1.6 });
        text.textContent = d[0];
        svg.append(text);
    });
    return svg;
}
