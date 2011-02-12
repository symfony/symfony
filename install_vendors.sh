#!/bin/sh

cd $(dirname $0)

# initialization
if [ "$1" = "--reinstall" ]; then
    rm -rf vendor
fi

mkdir -p vendor && cd vendor

##
# @param destination directory (e.g. "doctrine")
# @param URL of the git remote (e.g. git://github.com/doctrine/doctrine2.git)
#
install_git()
{
    INSTALL_DIR=$1
    SOURCE_URL=$2
    if [ -d $INSTALL_DIR ]; then
        cd $INSTALL_DIR
        git pull
        cd ..
    else
        git clone $SOURCE_URL $INSTALL_DIR
    fi
}

# Doctrine ORM
install_git doctrine git://github.com/doctrine/doctrine2.git

# Doctrine Data Fixtures Extension
install_git doctrine-data-fixtures git://github.com/doctrine/data-fixtures

# Doctrine DBAL
install_git doctrine-dbal git://github.com/doctrine/dbal.git

# Doctrine Common
install_git doctrine-common git://github.com/doctrine/common.git

# Doctrine migrations
install_git doctrine-migrations git://github.com/doctrine/migrations.git

# Doctrine MongoDB
install_git doctrine-mongodb git://github.com/doctrine/mongodb.git

# Doctrine MongoDB
install_git doctrine-mongodb-odm git://github.com/doctrine/mongodb-odm.git

# Swiftmailer
install_git swiftmailer git://github.com/swiftmailer/swiftmailer.git

# Twig
install_git twig git://github.com/fabpot/Twig.git

# Zend Framework
install_git zend git://github.com/zendframework/zf2.git
