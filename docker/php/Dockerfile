FROM php:8.3-fpm-alpine

LABEL maintainer="VSISPanel <admin@vsispanel.local>"

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    libxml2-dev \
    linux-headers \
    $PHPIZE_DEPS \
    mysql-client \
    redis \
    supervisor \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        xml \
        opcache \
        sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Xdebug for development
ARG INSTALL_XDEBUG=false
RUN if [ ${INSTALL_XDEBUG} = true ]; then \
    pecl install xdebug && docker-php-ext-enable xdebug; \
fi

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure PHP
COPY php.ini /usr/local/etc/php/conf.d/custom.ini
COPY php-fpm.conf /usr/local/etc/php-fpm.d/zzz-custom.conf

# Set working directory
WORKDIR /var/www/html

# Create user for application
RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/sh -D www

# Set permissions
RUN chown -R www:www /var/www/html

# Switch to non-root user
USER www

EXPOSE 9000

CMD ["php-fpm"]
