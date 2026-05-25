FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . /app

# Create .env file from .env.example for build time
RUN cp .env.example .env

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Generate application key
RUN php artisan key:generate

# Remove .env file so Render environment variables take precedence
RUN rm .env

# Set permissions
RUN chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache

# Expose port 8080 for HTTP
EXPOSE 8080

# Start Laravel development server
CMD php artisan serve --host=0.0.0.0 --port=8080
