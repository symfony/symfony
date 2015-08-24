dnl $Id$
dnl config.m4 for extension symfony_eventdispatcher

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl If your extension references something external, use with:

dnl PHP_ARG_WITH(symfony_eventdispatcher, for symfony_eventdispatcher support,
dnl Make sure that the comment is aligned:
dnl [  --with-symfony_eventdispatcher             Include symfony_eventdispatcher support])

dnl Otherwise use enable:

PHP_ARG_ENABLE(symfony_eventdispatcher, whether to enable symfony_eventdispatcher support,
dnl Make sure that the comment is aligned:
[  --enable-symfony_eventdispatcher           Enable symfony_eventdispatcher support])

if test "$PHP_SYMFONY_EVENTDISPATCHER" != "no"; then
  dnl Write more examples of tests here...

  dnl # --with-symfony_eventdispatcher -> check with-path
  dnl SEARCH_PATH="/usr/local /usr"     # you might want to change this
  dnl SEARCH_FOR="/include/symfony_eventdispatcher.h"  # you most likely want to change this
  dnl if test -r $PHP_SYMFONY_EVENTDISPATCHER/$SEARCH_FOR; then # path given as parameter
  dnl   SYMFONY_EVENTDISPATCHER_DIR=$PHP_SYMFONY_EVENTDISPATCHER
  dnl else # search default path list
  dnl   AC_MSG_CHECKING([for symfony_eventdispatcher files in default path])
  dnl   for i in $SEARCH_PATH ; do
  dnl     if test -r $i/$SEARCH_FOR; then
  dnl       SYMFONY_EVENTDISPATCHER_DIR=$i
  dnl       AC_MSG_RESULT(found in $i)
  dnl     fi
  dnl   done
  dnl fi
  dnl
  dnl if test -z "$SYMFONY_EVENTDISPATCHER_DIR"; then
  dnl   AC_MSG_RESULT([not found])
  dnl   AC_MSG_ERROR([Please reinstall the symfony_eventdispatcher distribution])
  dnl fi

  dnl # --with-symfony_eventdispatcher -> add include path
  dnl PHP_ADD_INCLUDE($SYMFONY_EVENTDISPATCHER_DIR/include)

  dnl # --with-symfony_eventdispatcher -> check for lib and symbol presence
  dnl LIBNAME=symfony_eventdispatcher # you may want to change this
  dnl LIBSYMBOL=symfony_eventdispatcher # you most likely want to change this 

  dnl PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
  dnl [
  dnl   PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $SYMFONY_EVENTDISPATCHER_DIR/lib, SYMFONY_EVENTDISPATCHER_SHARED_LIBADD)
  dnl   AC_DEFINE(HAVE_SYMFONY_EVENTDISPATCHERLIB,1,[ ])
  dnl ],[
  dnl   AC_MSG_ERROR([wrong symfony_eventdispatcher lib version or lib not found])
  dnl ],[
  dnl   -L$SYMFONY_EVENTDISPATCHER_DIR/lib -lm
  dnl ])
  dnl
  dnl PHP_SUBST(SYMFONY_EVENTDISPATCHER_SHARED_LIBADD)

  PHP_NEW_EXTENSION(symfony_eventdispatcher, *.c, $ext_shared)
fi
