/* Búsqueda tolerante a errores de escritura: ignora acentos y mayúsculas y
   acepta palabras con una o dos letras de diferencia ("tenix", "sudadra"). */

export function normalize(text) {
    return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

/* Distancia de edición entre dos palabras, cortando en cuanto supera el límite. */
function distance(a, b, limit) {
    if (Math.abs(a.length - b.length) > limit) return limit + 1;
    let previous = Array.from({ length: b.length + 1 }, function (_, i) { return i; });
    for (let i = 1; i <= a.length; i++) {
        const current = [i];
        let best = i;
        for (let j = 1; j <= b.length; j++) {
            const cost = a[i - 1] === b[j - 1] ? 0 : 1;
            current[j] = Math.min(previous[j] + 1, current[j - 1] + 1, previous[j - 1] + cost);
            best = Math.min(best, current[j]);
        }
        if (best > limit) return limit + 1;
        previous = current;
    }
    return previous[b.length];
}

function tolerance(word) {
    if (word.length <= 3) return 0;
    return word.length <= 6 ? 1 : 2;
}

/* Puntaje de qué tan bien coincide un término con las palabras del texto:
   2 = la contiene, 1 = parecida, 0 = no aparece. */
function termScore(term, words, text) {
    if (text.indexOf(term) >= 0) return 2;
    const limit = tolerance(term);
    if (!limit) return 0;
    return words.some(function (w) {
        return distance(term, w.slice(0, term.length + limit), limit) <= limit;
    }) ? 1 : 0;
}

/* Devuelve el puntaje (0 si algún término no coincide). */
export function matchScore(query, text) {
    const terms = normalize(query).split(/\s+/).filter(Boolean);
    if (!terms.length) return 1;
    const haystack = normalize(text);
    const words = haystack.split(/[^a-z0-9ñ]+/).filter(Boolean);
    let total = 0;
    for (const term of terms) {
        const score = termScore(term, words, haystack);
        if (!score) return 0;
        total += score;
    }
    return total;
}

export function productText(p, departments) {
    return [p.name, p.description, p.category, departments ? departments[p.department] : ''].join(' ');
}
