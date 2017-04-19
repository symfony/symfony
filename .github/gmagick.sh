#!/bin/bash

set -xe

if [ -z "$1" ]
then
    echo "You must provide the PHP version as first argument"
    exit 1
fi

if [ -z "$2" ]
then
    echo "You must provide the php.ini path as second argument"
    exit 1
fi

PHP_VERSION=$1
PHP_INI_FILE=$2

GRAPHICSMAGIC_VERSION="1.3.23"
if [ $PHP_VERSION = '7.0' ] || [ $PHP_VERSION = '7.1' ]
then
  GMAGICK_VERSION="2.0.4RC1"
else
  GMAGICK_VERSION="1.1.7RC2"
fi

mkdir -p cache
cd cache

if [ ! -e ./GraphicsMagick-$GRAPHICSMAGIC_VERSION ]
then
    wget http://78.108.103.11/MIRROR/ftp/GraphicsMagick/1.3/GraphicsMagick-$GRAPHICSMAGIC_VERSION.tar.xz
    tar -xf GraphicsMagick-$GRAPHICSMAGIC_VERSION.tar.xz
    rm GraphicsMagick-$GRAPHICSMAGIC_VERSION.tar.xz
    cd GraphicsMagick-$GRAPHICSMAGIC_VERSION
    ./configure --prefix=$HOME/opt/gmagick --enable-shared --with-lcms2
    make -j
else
    cd GraphicsMagick-$GRAPHICSMAGIC_VERSION
fi

make install
cd ..

if [ ! -e ./gmagick-$GMAGICK_VERSION ]
then
    wget https://pecl.php.net/get/gmagick-$GMAGICK_VERSION.tgz
    tar -xzf gmagick-$GMAGICK_VERSION.tgz
    rm gmagick-$GMAGICK_VERSION.tgz
    cd gmagick-$GMAGICK_VERSION
    phpize
    ./configure --with-gmagick=$HOME/opt/gmagick
    make -j
else
    cd gmagick-$GMAGICK_VERSION
fi

make install
echo "extension=`pwd`/modules/gmagick.so" >> $PHP_INI_FILE
php --ri gmagick
