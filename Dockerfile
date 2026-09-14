FROM php:8.2-apache

# Install system dependencies required for PHP extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Install and enable PHP curl extension
RUN docker-php-ext-install curl

# Set working directory
WORKDIR /var/www/html

# Copy project files into the container
COPY . /var/www/html/

# Expose the mandatory port required by Railway / Apache
EXPOSE 7860

# Configure Apache to listen on port 7860 instead of 80
RUN sed -i 's/80/7860/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Start Apache in the foreground
CMD ["apache2-foreground"]