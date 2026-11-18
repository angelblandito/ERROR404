/*
 * URL de la API.
 *   - Servida por Apache de XAMPP (computadora, celular en la misma red o
 *     túnel público): la API está en la misma dirección que la página,
 *     en <carpeta de la página>/api/v1/php.
 *   - Abierta como archivo (file://): se usa "php -S localhost:8080".
 *   - Publicada en Azure Static Web Apps: se usa AZURE_API_URL.
 */
(function () {
    var AZURE_API_URL = 'https://jam-api.azurewebsites.net';
    var host = window.location.hostname;
    var folder = window.location.pathname.replace(/[^/]*$/, '');
    var apiUrl;

    if (window.location.protocol === 'file:') {
        apiUrl = 'http://localhost:8080';
    } else if (/\.azurestaticapps\.net$/.test(host)) {
        apiUrl = AZURE_API_URL;
    } else {
        apiUrl = window.location.origin + folder + 'api/v1/php';
    }

    window.JAM_CONFIG = { API_URL: apiUrl };
})();
