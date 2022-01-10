FROM ubuntu:21.04

ARG DEBIAN_FRONTEND=noninteractive
ARG HOST_UID=1000
ARG HOST_GID=1000

RUN apt-get update && apt-get install -y software-properties-common curl zip git
RUN apt-get install -y supervisor
RUN apt-get install -y nginx gettext-base
RUN add-apt-repository ppa:ondrej/php
RUN apt-get update
RUN apt-get install -y php8.1 php8.1-cli php8.1-fpm php8.1-pdo php8.1-mysql php8.1-bcmath php8.1-gmp php8.1-mbstring php8.1-curl

RUN mkdir -p /run/php && touch /run/php/php8.1-fpm.sock && touch /run/php/php8.1-fpm.pid

WORKDIR /root
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer

COPY ./app/services/admin/nginx.conf /etc/nginx/nginx.template.conf
COPY ./app/services/admin/supervisord.conf /etc/supervisord.conf
COPY ./app/services/admin/entrypoint.sh /root/entrypoint.sh
COPY ./app/services/admin/php8.1-fpm.conf /etc/php/8.1/fpm/pool.d/www.conf

WORKDIR /etc/nginx
RUN rm -rf sites-available sites-enabled nginx.conf

RUN groupadd -g $HOST_GID comely-io
RUN adduser --disabled-password --gecos '' -u $HOST_UID -gid $HOST_GID comely-io

USER comely-io
WORKDIR /home/comely-io/
COPY ./ssl ssl/
COPY ./config admin/config/
COPY ./docker admin/docker/
COPY ./app/common admin/common/
COPY ./app/services/admin/composer.json admin/composer.json
COPY ./app/services/admin/src admin/src/
COPY ./app/services/admin/www admin/www/

USER root
RUN apt-get install iputils-ping
RUN chown -R comely-io:comely-io /home/comely-io/
RUN chmod +x /home/comely-io

USER root
WORKDIR /root
RUN chmod +x entrypoint.sh
ENTRYPOINT ["./entrypoint.sh"]
