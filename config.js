/*
 * URL de la API (api/v3).
 *   - Servida por Apache (XAMPP en la computadora o en la máquina virtual de
 *     Azure, directo o detrás de Caddy con https): la API está en la misma
 *     dirección que la página.
 *   - Abierta como archivo (file://): se usa "php -S localhost:8080 api/v3/index.php".
 */
(function () {
    var folder = window.location.pathname.replace(/[^/]*$/, '');
    var apiUrl = window.location.protocol === 'file:'
        ? 'http://localhost:8080'
        : window.location.origin + folder + 'api/v3';

    window.JAM_CONFIG = { API_URL: apiUrl };
})();
