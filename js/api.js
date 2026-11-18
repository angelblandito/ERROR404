/* Cliente de la API: agrega el token de sesión y unifica el manejo de errores. */

export const API_URL = (window.JAM_CONFIG && window.JAM_CONFIG.API_URL) || 'http://localhost:8080';

const TOKEN_KEY = 'jam_session';

export function getToken() {
    try {
        return localStorage.getItem(TOKEN_KEY);
    } catch (e) {
        return null;
    }
}

export function setToken(token) {
    try {
        if (token) {
            localStorage.setItem(TOKEN_KEY, token);
        } else {
            localStorage.removeItem(TOKEN_KEY);
        }
    } catch (e) {
        /* sin localStorage */
    }
}

export class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.status = status;
    }
}

export async function api(path, options = {}) {
    const init = { method: options.method || 'GET', headers: {} };
    if (options.body !== undefined) {
        init.headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(options.body);
    }
    const token = getToken();
    if (token) {
        init.headers.Authorization = 'Bearer ' + token;
    }

    let response;
    try {
        response = await fetch(API_URL + path, init);
    } catch (e) {
        throw new ApiError('No hay conexión con la tienda. Revisa tu internet e inténtalo de nuevo.', 0);
    }

    let payload;
    try {
        payload = await response.json();
    } catch (e) {
        throw new ApiError('La tienda respondió con un formato inesperado (HTTP ' + response.status + ').', response.status);
    }

    if (response.status === 401 && token) {
        setToken(null);
    }
    if (!response.ok || payload.success === false) {
        throw new ApiError(payload.message || 'Error HTTP ' + response.status, response.status);
    }
    return payload.data;
}
