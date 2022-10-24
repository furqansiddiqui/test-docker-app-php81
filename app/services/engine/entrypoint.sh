#!/bin/bash
cd /home/comely-io/engine
composer update
chown -R comely-io:comely-io /home/comely-io/engine/vendor
su comely-io
cd /home/comely-io/engine/src
./console app_daemon
