FROM ubuntu:22.04

ARG DEBIAN_FRONTEND=noninteractive
ARG HOST_UID=1000
ARG HOST_GID=1000

RUN apt-get update && apt-get install -y software-properties-common curl zip git
RUN add-apt-repository ppa:ondrej/php
RUN apt-get update
RUN apt-get install -y php8.1 php8.1-cli php8.1-pdo php8.1-mysql php8.1-bcmath php8.1-gmp php8.1-mbstring php8.1-curl
RUN apt-get install -y mariadb-client

WORKDIR /root
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer

COPY ./app/services/engine/entrypoint.sh /root/entrypoint.sh

RUN groupadd -g $HOST_GID comely-io
RUN adduser --disabled-password --gecos '' -u $HOST_UID -gid $HOST_GID comely-io

USER comely-io
WORKDIR /home/comely-io/engine
COPY ./config config/
COPY ./app/common common/
COPY ./app/services/engine/composer.json composer.json
COPY ./app/services/engine/src src/

USER root
RUN chown -R comely-io:comely-io /home/comely-io/
RUN chmod +x /home/comely-io/engine/src/console

USER root
WORKDIR /root
RUN chmod +x entrypoint.sh
ENTRYPOINT ["./entrypoint.sh"]
