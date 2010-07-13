#!/bin/sh

# initialization
if [ -d "vendor" ]; then
  rm -rf vendor/*
else
  mkdir vendor
fi

cd vendor

# Doctrine ORM
git clone git://github.com/doctrine/doctrine2.git doctrine

# Doctrine DBAL
git clone git://github.com/doctrine/dbal.git doctrine-dbal

# Doctrine Common
git clone git://github.com/doctrine/common.git doctrine-common

# Doctrine migrations
git clone git://github.com/doctrine/migrations.git doctrine-migrations

# Doctrine MongoDB
git clone git://github.com/doctrine/mongodb-odm.git doctrine-mongodb

# Propel
svn co http://svn.propelorm.org/branches/1.5/ propel

# Phing
svn co http://svn.phing.info/tags/2.3.3 phing

# Swiftmailer
git clone git://github.com/swiftmailer/swiftmailer.git swiftmailer

# Twig
git clone git://github.com/fabpot/Twig.git twig

# Zend Framework
git clone git://github.com/zendframework/zf2.git zend
