FROM php:8.2-cli-alpine

WORKDIR /app

COPY index.php .

EXPOSE 10000

CMD php -S 0.0.0.0:10000 -t /app
