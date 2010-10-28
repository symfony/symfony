#!/bin/sh

CURRENT=`pwd`/vendor

# Doctrine ORM
cd $CURRENT/doctrine && git pull

# Doctrine Data Fixtures Extension
cd $CURRENT/doctrine-data-fixtures && git pull

# Doctrine DBAL
cd $CURRENT/doctrine-dbal && git pull

# Doctrine common
cd $CURRENT/doctrine-common && git pull

# Doctrine migrations
cd $CURRENT/doctrine-migrations && git pull

# Doctrine MongoDB
cd $CURRENT/doctrine-mongodb && git pull

# Swiftmailer
cd $CURRENT/swiftmailer && git pull

# Twig
cd $CURRENT/twig && git pull

# Zend Framework
cd $CURRENT/zend && git pull
