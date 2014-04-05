/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#ifndef PHP_SYMFONY_DEBUG_H
#define PHP_SYMFONY_DEBUG_H

extern zend_module_entry symfony_debug_module_entry;
#define phpext_symfony_debug_ptr &symfony_debug_module_entry

#define PHP_SYMFONY_DEBUG_VERSION "1.0"

#ifdef PHP_WIN32
#	define PHP_SYMFONY_DEBUG_API __declspec(dllexport)
#elif defined(__GNUC__) && __GNUC__ >= 4
#	define PHP_SYMFONY_DEBUG_API __attribute__ ((visibility("default")))
#else
#	define PHP_SYMFONY_DEBUG_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

ZEND_BEGIN_MODULE_GLOBALS(symfony_debug)
	intptr_t req_rand_init;
ZEND_END_MODULE_GLOBALS(symfony_debug)

PHP_MINIT_FUNCTION(symfony_debug);
PHP_MSHUTDOWN_FUNCTION(symfony_debug);
PHP_RINIT_FUNCTION(symfony_debug);
PHP_RSHUTDOWN_FUNCTION(symfony_debug);
PHP_MINFO_FUNCTION(symfony_debug);
PHP_GINIT_FUNCTION(symfony_debug);
PHP_GSHUTDOWN_FUNCTION(symfony_debug);

PHP_FUNCTION(symfony_zval_info);

static char *_symfony_debug_memory_address_hash(void *);
static const char *_symfony_debug_zval_type(zval *);
static const char* _symfony_debug_get_resource_type(long);
static int _symfony_debug_get_resource_refcount(long);

#ifdef ZTS
#define SYMFONY_DEBUG_G(v) TSRMG(symfony_debug_globals_id, zend_symfony_debug_globals *, v)
#else
#define SYMFONY_DEBUG_G(v) (symfony_debug_globals.v)
#endif

#endif	/* PHP_SYMFONY_DEBUG_H */
