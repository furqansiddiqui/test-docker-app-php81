#!/bin/bash
export ESC='$'
envsubst < /etc/nginx/nginx.template.conf > /etc/nginx/nginx.conf
cd /home/comely-io/public
composer update
chown -R comely-io:comely-io /home/comely-io/public/vendor
cd ~
/usr/bin/supervisord -c /etc/supervisord.conf
