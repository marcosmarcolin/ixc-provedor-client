ARG PHP_VERSION=7.4
# Alpine: the Debian base of php:7.4 (bullseye) is EOL and its security pool answers 404.
FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --no-cache unzip git

# pcov for the coverage report (composer coverage).
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
