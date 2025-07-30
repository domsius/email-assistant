FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    cron \
    libicu-dev \
    g++ \
    msmtp \
    msmtp-mta \
    # Add SSH client for deployment
    openssh-client

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath gd zip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js and npm (latest LTS version)
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
RUN apt-get update && apt-get install -y nodejs

# Install additional dependencies for Vite
RUN apt-get update && apt-get install -y \
    python3 \
    make \
    g++

# Setup SSH directory with proper permissions
RUN rm -rf /root/.ssh && \
    mkdir -p /root/.ssh && \
    chmod 700 /root/.ssh && \
    touch /root/.ssh/known_hosts && \
    chmod 600 /root/.ssh/known_hosts && \
    touch /root/.ssh/config && \
    chmod 600 /root/.ssh/config

# Add SSH configuration
RUN echo "StrictHostKeyChecking no" >> /root/.ssh/config && \
    echo "UserKnownHostsFile /dev/null" >> /root/.ssh/config

# Verify Node.js and npm installation
RUN node --version && npm --version

# Set working directory
WORKDIR /var/www

# Copy existing application directory contents
COPY . /var/www

# Set correct permissions
RUN chown -R www-data:www-data /var/www
RUN chmod -R 755 /var/www
RUN chmod -R 777 /var/www/storage /var/www/bootstrap/cache

# Install Composer dependencies
RUN composer install
RUN composer require deployer/deployer --dev

# Install NPM dependencies and build assets
RUN npm install
RUN npm run build

# Change ownership of our applications
RUN chown -R www-data:www-data /var/www

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]