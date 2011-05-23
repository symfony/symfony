@echo off
cd %~dp0

rem initialization
if "%1" == "--reinstall" rmdir /s /q vendor

mkdir vendor
cd vendor

goto Install_Assetic

:install_git
    if "%VERSION%" == "" set VERSION=origin/HEAD

    if NOT EXIST "%VENDOR_DIR%" git clone %VENDOR_URL% %VENDOR_DIR%

    cd %VENDOR_DIR%
    git fetch origin
    git reset --hard %VERSION%
    cd ..
    goto %NEXT_STEP%

:Install_Assetic
set VENDOR_DIR=assetic
set VENDOR_URL=git://github.com/kriswallsmith/assetic.git
set VERSION=
set NEXT_STEP=Install_Doctrine_ORM
goto install_git

:Install_Doctrine_ORM
set VENDOR_DIR=doctrine
set VENDOR_URL=git://github.com/doctrine/doctrine2.git
set VERSION=2.0.5
set NEXT_STEP=Install_Doctrine_DBAL
goto install_git

:Install_Doctrine_DBAL
set VENDOR_DIR=doctrine-dbal
set VENDOR_URL=git://github.com/doctrine/dbal.git
set VERSION=2.0.5
set NEXT_STEP=Install_Doctrine_Common
goto install_git

:Install_Doctrine_Common
set VENDOR_DIR=doctrine-common
set VENDOR_URL=git://github.com/doctrine/common.git
set VERSION=origin/3.0.x
set NEXT_STEP=Install_Doctrine_Migrations
goto install_git

:Install_Doctrine_Migrations
set VENDOR_DIR=doctrine-migrations
set VENDOR_URL=git://github.com/doctrine/migrations.git
set VERSION=
set NEXT_STEP=Install_Monolog
goto install_git

:Install_Monolog
set VENDOR_DIR=monolog
set VENDOR_URL=git://github.com/Seldaek/monolog.git
set VERSION=
set NEXT_STEP=Install_Swiftmailer
goto install_git

:Install_Swiftmailer
set VENDOR_DIR=swiftmailer
set VENDOR_URL=git://github.com/swiftmailer/swiftmailer.git
set VERSION=origin/4.1
set NEXT_STEP=Install_Twig
goto install_git

:Install_Twig
set VENDOR_DIR=twig
set VENDOR_URL=git://github.com/fabpot/Twig.git
set VERSION=
set NEXT_STEP=end
goto install_git

:end