#!/bin/sh

CURRENT=`pwd`/vendor

# Doctrine ORM
cd $CURRENT/doctrine && git pull

# Doctrine DBAL
cd $CURRENT/doctrine-dbal && git pull

# Doctrine common
cd $CURRENT/doctrine-common && git pull

# Doctrine migrations
cd $CURRENT/doctrine-migrations && git pull

# Doctrine MongoDB
cd $CURRENT/doctrine-mongodb && git pull

# Propel
cd $CURRENT/propel && svn up

# Phing
cd $CURRENT/phing && svn up

# Swiftmailer
cd $CURRENT/swiftmailer && git pull

# Twig
cd $CURRENT/twig && git pull

# Zend Framework
cd $CURRENT/zend && git pull
