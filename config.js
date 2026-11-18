/*
 * URL de la API.
 *   - En local (http://localhost/ERROR404/) se usa la API de "php -S localhost:8080".
 *   - Publicado en Azure, se usa la URL del App Service de la API.
 * Cambiar AZURE_API_URL por la URL real después de crear el App Service.
 */
(function () {
    var AZURE_API_URL = 'https://jam-api.azurewebsites.net';
    var host = window.location.hostname;
    var isLocal = window.location.protocol === 'file:' || host === 'localhost' || host === '127.0.0.1';

    window.JAM_CONFIG = {
        API_URL: isLocal ? 'http://localhost:8080' : AZURE_API_URL
    };
})();
