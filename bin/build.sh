#!/bin/bash
SCRIPT=`realpath $0`
SCRIPT_PATH=`dirname $SCRIPT`
HOST_UID=`id -u`
HOST_GID=`id -g`

if [ "$HOST_UID" -eq 0 ]; then
  echo -e "\e[31mERROR:\e[0m Cannot build as \"\e[31mroot\e[0m\" user";
  exit
fi

cd $SCRIPT_PATH
cd ../

if [ -z "$1" ];
  then
  echo -e "\e[33mWhich configuration to build?\e[0m (\e[36mfull\e[0m|\e[36mlite\e[0m)"
  read -p "configuration: " DOCKER_COMPOSE
fi

APP_CONFIG_DIR="config/"
if [[ ! -d "$APP_CONFIG_DIR" ]]; then
  echo -e "\e[31mERROR:\e[0m Config directory \"\e[36m${APP_CONFIG_DIR}\e[0m\" does not exist";
  exit
fi

APP_TMP_DIR="tmp/"
if [[ ! -d "$APP_TMP_DIR" ]]; then
  mkdir "tmp"
  chmod -R 777 tmp
fi

if [[ -z "$DOCKER_COMPOSE" ]]; then
  DOCKER_COMPOSE=$1
fi

DOCKER_ENV_FILE=".env";
if [[ ! -f "$DOCKER_ENV_FILE" ]]; then
  echo -e "\e[31mERROR:\e[0m Environment configuration file \"\e[36m${DOCKER_ENV_FILE}\e[0m\" does not exist";
  exit
fi

./bin/services.sh down

truncate -s 0 log/admin/*.log
truncate -s 0 log/public/*.log
truncate -s 0 log/engine/*.log

rm -rf app/services/public/vendor
rm -rf app/services/admin/vendor
rm -rf app/services/engine/vendor
rm engine.sh

cp .env docker/.env
cd docker/
DOCKER_COMPOSE_FILE="docker-compose.$DOCKER_COMPOSE.yml";

if [[ ! -f "$DOCKER_COMPOSE_FILE" ]]; then
  cd ../
  echo -e "\e[31mERROR:\e[0m Docker compose file \"\e[36m${DOCKER_COMPOSE}\e[0m\" does not exist";
  exit
fi

docker compose -f docker-compose.yml -f ${DOCKER_COMPOSE_FILE} build --build-arg HOST_UID=${HOST_UID} --build-arg HOST_GID=${HOST_GID}
docker compose -f docker-compose.yml -f ${DOCKER_COMPOSE_FILE} up -d

cd ../
echo -e '#!/bin/bash
SCRIPT=`realpath $0`
SCRIPT_PATH=`dirname $SCRIPT`
cd $SCRIPT_PATH/docker
EXEC_CMD="/home/comely-io/engine/src/console $@"
docker compose exec -T engine /bin/su comely-io -c "/bin/bash $EXEC_CMD"
cd ../' > engine.sh

chmod +x engine.sh
./bin/services.sh ps

echo -e "\e[33m";
echo -n "Waiting for services to come  ";

SERVICE_PUBLIC_API_ID=`./bin/services.sh ps -q public_api`
SERVICE_ADMIN_API_ID=`./bin/services.sh ps -q admin_api`
SERVICE_ENGINE_ID=`./bin/services.sh ps -q engine`

while [ "`docker inspect -f {{.State.Running}} $SERVICE_ENGINE_ID`" != "true" ]; do     sleep 1; done
echo -n ".";
while [ "`docker inspect -f {{.State.Running}} $SERVICE_PUBLIC_API_ID`" != "true" ]; do     sleep 1; done
echo -n ".";
while [ "`docker inspect -f {{.State.Running}} $SERVICE_ADMIN_API_ID`" != "true" ]; do     sleep 1; done
echo -n ".";
echo -e "\e[0m";
echo -e "";

sleep 10;
./engine.sh install
./engine.sh default_config
