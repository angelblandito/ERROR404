/*
 * URL de la API.
 *   - Servida por Apache (XAMPP en la computadora o en la máquina virtual de
 *     Azure, directo o detrás de Caddy con https): la API está en la misma
 *     dirección que la página.
 *       api/v3       API de la tienda. Es la que usa la página.
 *       api/v2       Respaldo; se elige agregando ?api=v2 a la dirección.
 *       api/v1/php   Respaldo; se elige con ?api=v1.
 *                    Con ?api=v3 se regresa a la v3. La elección se recuerda en este navegador.
 *   - Abierta como archivo (file://): se usa "php -S localhost:8080 api/v3/index.php".
 */
(function () {
    var versions = { v1: 'api/v1/php', v2: 'api/v2', v3: 'api/v3' };
    var version = 'v3';

    try {
        var asked = new URLSearchParams(window.location.search).get('api');
        if (asked && versions[asked]) {
            window.localStorage.setItem('jam_api_version', asked);
        }
        var saved = window.localStorage.getItem('jam_api_version');
        if (saved && versions[saved]) {
            version = saved;
        }
    } catch (e) {
        /* Sin localStorage (modo privado estricto): se usa la versión por defecto. */
    }

    var folder = window.location.pathname.replace(/[^/]*$/, '');
    var apiUrl = window.location.protocol === 'file:'
        ? 'http://localhost:8080'
        : window.location.origin + folder + versions[version];

    window.JAM_CONFIG = { API_URL: apiUrl, API_VERSION: version };
})();
