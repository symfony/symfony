#!/bin/sh

DIR=`php -r "echo realpath(dirname('$0'));"`

cp $DIR/../../../FrameworkBundle/Resources/public/css/body.css $DIR/../views/Profiler/body.css.twig
cp $DIR/../../../FrameworkBundle/Resources/public/css/exception.css $DIR/../views/Collector/exception.css.twig
