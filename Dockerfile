FROM hyperf/hyperf:8.2-alpine-v3.18-swoole


ENV timezone="America/Sao_Paulo"


RUN set -ex \
    # define the docker timezone
    && ln -sf /usr/share/zoneinfo/${timezone} /etc/localtime \
    && echo "${timezone}" > /etc/timezone


RUN set -ex \
    && apk --no-cache add php82-pdo_pgsql


WORKDIR /api

ADD ./api .

# RUN composer install --prefer-dist --no-dev --optimize-autoloader

EXPOSE 9501

RUN "/bin/bash /api/api/run.sh"