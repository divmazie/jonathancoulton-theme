FROM wordpress:4.7


# from https://github.com/docker-library/docs/blob/master/php/README.md#how-to-install-more-php-extensions
# also i like to use vim
RUN DEBIAN_FRONTEND=noninteractive apt-get update &&  DEBIAN_FRONTEND=noninteractive apt-get install -y --force-yes \
    libzip2 libzip-dev vim

RUN docker-php-ext-install zip
