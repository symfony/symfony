#!/bin/bash

set -xe

if [ -z "$1" ]
then
    echo "You must provide the php.ini path as first argument"
    exit 1
fi

PHP_INI_FILE=$1

IMAGEMAGICK_VERSION="6.8.9-10"
IMAGICK_VERSION="3.4.3"

mkdir -p cache
cd cache

if [ ! -e ./ImageMagick-$IMAGEMAGICK_VERSION ]
then
    wget https://www.imagemagick.org/download/releases/ImageMagick-$IMAGEMAGICK_VERSION.tar.xz
    tar -xf ImageMagick-$IMAGEMAGICK_VERSION.tar.xz
    rm ImageMagick-$IMAGEMAGICK_VERSION.tar.xz
    cd ImageMagick-$IMAGEMAGICK_VERSION
    ./configure --prefix=$HOME/opt/imagemagick
    make -j
else
    cd ImageMagick-$IMAGEMAGICK_VERSION
fi

make install
export PKG_CONFIG_PATH=$PKG_CONFIG_PATH:$HOME/opt/imagemagick/lib/pkgconfig
ln -s $HOME/opt/imagemagick/include/ImageMagick-6 $HOME/opt/imagemagick/include/ImageMagick
cd ..

if [ ! -e ./imagick-$IMAGICK_VERSION ]
then
    wget https://pecl.php.net/get/imagick-$IMAGICK_VERSION.tgz
    tar -xzf imagick-$IMAGICK_VERSION.tgz
    rm imagick-$IMAGICK_VERSION.tgz
    cd imagick-$IMAGICK_VERSION
    phpize
    ./configure --with-imagick=$HOME/opt/imagemagick
    make -j
else
    cd imagick-$IMAGICK_VERSION
fi

make install
echo "extension=`pwd`/modules/imagick.so" >> $PHP_INI_FILE
php --ri imagick
