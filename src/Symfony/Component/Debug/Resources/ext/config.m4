dnl $Id$
dnl config.m4 for extension symfony_debug

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl If your extension references something external, use with:

dnl PHP_ARG_WITH(symfony_debug, for symfony_debug support,
dnl Make sure that the comment is aligned:
dnl [  --with-symfony_debug             Include symfony_debug support])

dnl Otherwise use enable:

PHP_ARG_ENABLE(symfony_debug, whether to enable symfony_debug support,
dnl Make sure that the comment is aligned:
[  --enable-symfony_debug           Enable symfony_debug support])

if test "$PHP_SYMFONY_DEBUG" != "no"; then
  dnl Write more examples of tests here...

  dnl # --with-symfony_debug -> check with-path
  dnl SEARCH_PATH="/usr/local /usr"     # you might want to change this
  dnl SEARCH_FOR="/include/symfony_debug.h"  # you most likely want to change this
  dnl if test -r $PHP_SYMFONY_DEBUG/$SEARCH_FOR; then # path given as parameter
  dnl   SYMFONY_DEBUG_DIR=$PHP_SYMFONY_DEBUG
  dnl else # search default path list
  dnl   AC_MSG_CHECKING([for symfony_debug files in default path])
  dnl   for i in $SEARCH_PATH ; do
  dnl     if test -r $i/$SEARCH_FOR; then
  dnl       SYMFONY_DEBUG_DIR=$i
  dnl       AC_MSG_RESULT(found in $i)
  dnl     fi
  dnl   done
  dnl fi
  dnl
  dnl if test -z "$SYMFONY_DEBUG_DIR"; then
  dnl   AC_MSG_RESULT([not found])
  dnl   AC_MSG_ERROR([Please reinstall the symfony_debug distribution])
  dnl fi

  dnl # --with-symfony_debug -> add include path
  dnl PHP_ADD_INCLUDE($SYMFONY_DEBUG_DIR/include)

  dnl # --with-symfony_debug -> check for lib and symbol presence
  dnl LIBNAME=symfony_debug # you may want to change this
  dnl LIBSYMBOL=symfony_debug # you most likely want to change this 

  dnl PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
  dnl [
  dnl   PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $SYMFONY_DEBUG_DIR/lib, SYMFONY_DEBUG_SHARED_LIBADD)
  dnl   AC_DEFINE(HAVE_SYMFONY_DEBUGLIB,1,[ ])
  dnl ],[
  dnl   AC_MSG_ERROR([wrong symfony_debug lib version or lib not found])
  dnl ],[
  dnl   -L$SYMFONY_DEBUG_DIR/lib -lm
  dnl ])
  dnl
  dnl PHP_SUBST(SYMFONY_DEBUG_SHARED_LIBADD)

  PHP_NEW_EXTENSION(symfony_debug, symfony_debug.c, $ext_shared)
fi
