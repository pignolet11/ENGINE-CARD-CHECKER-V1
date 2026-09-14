FROM php:8.2-cli
RUN docker-php-ext-install curl
WORKDIR /app
COPY . /app
RUN mkdir -p /app/logs && chmod -R 777 /app/logs
ENV PORT=10000
EXPOSE 10000
CMD php -S 0.0.0.0:$PORT