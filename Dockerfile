FROM ubuntu:latest
LABEL authors="nikostapp"

ENTRYPOINT ["top", "-b"]

# Используем официальный образ PHP с Apache
FROM php:8.1-apache

# Устанавливаем необходимые расширения PHP
RUN docker-php-ext-install pdo_mysql

# Копируем файлы проекта в контейнер
COPY . /var/www/html

# Устанавливаем права на директорию
RUN chown -R www-data:www-data /var/www/html

# Включаем mod_rewrite для Apache
RUN a2enmod rewrite

# Перезапускаем Apache
RUN service apache2 restart