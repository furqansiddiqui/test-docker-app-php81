#!/bin/bash
cd /home/comely-io/engine
composer update
chown -R comely-io:comely-io /home/comely-io/engine/vendor
/usr/bin/supervisord -c /etc/supervisord.conf
