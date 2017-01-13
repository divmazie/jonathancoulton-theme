FROM php:5.6-apache

# install the PHP extensions we need
RUN docker-php-ext-install mysqli mbstring

# need bzip2 to decode pma archive
RUN DEBIAN_FRONTEND=noninteractive apt-get update &&  DEBIAN_FRONTEND=noninteractive apt-get install -y --force-yes \
    bzip2

ENV PHPMYADMIN_VERSION 4.4.13
ENV PHPMYADMIN_DOCUMENT_ROOT /var/www/html/
ENV PHPMYADMIN_CONFIG_FILE_PATH $PHPMYADMIN_DOCUMENT_ROOT/config.inc.php
RUN rm -rf $PHPMYADMIN_DOCUMENT_ROOT && mkdir -p $PHPMYADMIN_DOCUMENT_ROOT
RUN curl -SL https://files.phpmyadmin.net/phpMyAdmin/${PHPMYADMIN_VERSION}/phpMyAdmin-${PHPMYADMIN_VERSION}-english.tar.bz2 | \
    tar xjv --strip-components 1 -C $PHPMYADMIN_DOCUMENT_ROOT

COPY ./config.inc.php $PHPMYADMIN_CONFIG_FILE_PATH