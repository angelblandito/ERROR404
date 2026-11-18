#!/bin/bash
# Comando de inicio de Azure App Service (Linux, PHP 8.x):
#   bash /home/site/wwwroot/azure/startup.sh
# Enruta todas las peticiones a index.php, igual que "php -S localhost:8080 index.php" en local.
cp /home/site/wwwroot/azure/nginx.conf /etc/nginx/sites-available/default
service nginx reload
