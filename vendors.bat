@echo off
setlocal

cd /d %~dp0

:: initialization
if [%1] == [--reinstall] (
    if exist vendor (
        rd /s /q vendor
    )
)

if not exist vendor (
    mkdir vendor
)

cd vendor

:: Assetic
call:install_git assetic git://github.com/kriswallsmith/assetic.git

:: Doctrine ORM
call:install_git doctrine git://github.com/doctrine/doctrine2.git 2.0.5

:: Doctrine DBAL
call:install_git doctrine-dbal git://github.com/doctrine/dbal.git 2.0.5

:: Doctrine Common
call:install_git doctrine-common git://github.com/doctrine/common.git origin/3.0.x

:: Doctrine migrations
call:install_git doctrine-migrations git://github.com/doctrine/migrations.git

:: Monolog
call:install_git monolog git://github.com/Seldaek/monolog.git

:: Swiftmailer
call:install_git swiftmailer git://github.com/swiftmailer/swiftmailer.git origin/4.1

:: Twig
call:install_git twig git://github.com/fabpot/Twig.git

endlocal
goto:eof

::::
:: @param destination directory (e.g. "doctrine")
:: @param URL of the git remote (e.g. http://github.com/doctrine/doctrine2.git)
:: @param revision to point the head (e.g. origin/HEAD)
::
:install_git
    set INSTALL_DIR=%1
    set SOURCE_URL=%2
    set REV=%3

    if [%REV%] == [] (
        set REV="origin/HEAD"
    )

    if not exist %INSTALL_DIR% (
        git clone %SOURCE_URL% %INSTALL_DIR%
    )
	
    cd %INSTALL_DIR%
    git fetch origin
    git reset --hard %REV%
    cd ..
goto:eof
