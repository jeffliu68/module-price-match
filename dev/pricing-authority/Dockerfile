FROM php:8.4-cli-alpine
WORKDIR /app
COPY index.php /app/index.php
EXPOSE 8080
# PHP's built-in server is multi-worker with PHP_CLI_SERVER_WORKERS, enough for a mock.
ENV PHP_CLI_SERVER_WORKERS=4 MOCK_API_KEY=demo-key
CMD ["php", "-S", "0.0.0.0:8080", "/app/index.php"]
