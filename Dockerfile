FROM php:cli-bullseye

RUN echo "deb http://deb.debian.org/debian experimental main" >> /etc/apt/sources.list \
    && apt-get update && apt-get install -y libffi-dev \
    && docker-php-ext-configure ffi --with-ffi \
    && docker-php-ext-install ffi