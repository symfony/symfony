/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#ifdef ZTS
#include "TSRM.h"
#endif
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_symfony_debug.h"
#include "ext/standard/php_rand.h"
#include "ext/standard/php_lcg.h"
#include "ext/spl/php_spl.h"
#include "Zend/zend_gc.h"
#include "Zend/zend_builtin_functions.h"
#include "Zend/zend_extensions.h" /* for ZEND_EXTENSION_API_NO */
#include "ext/standard/php_array.h"
#include "Zend/zend_interfaces.h"
#include "SAPI.h"

#define IS_PHP_53 ZEND_EXTENSION_API_NO == 220090626

ZEND_DECLARE_MODULE_GLOBALS(symfony_debug)

ZEND_BEGIN_ARG_INFO_EX(symfony_zval_arginfo, 0, 0, 2)
	ZEND_ARG_INFO(0, key)
	ZEND_ARG_ARRAY_INFO(0, array, 0)
	ZEND_ARG_INFO(0, options)
ZEND_END_ARG_INFO()

const zend_function_entry symfony_debug_functions[] = {
	PHP_FE(symfony_zval_info,	symfony_zval_arginfo)
	PHP_FE(symfony_debug_backtrace, NULL)
	PHP_FE_END
};

PHP_FUNCTION(symfony_debug_backtrace)
{
	if (zend_parse_parameters_none() == FAILURE) {
		return;
	}
#if IS_PHP_53
	zend_fetch_debug_backtrace(return_value, 1, 0 TSRMLS_CC);
#else
	zend_fetch_debug_backtrace(return_value, 1, 0, 0 TSRMLS_CC);
#endif

	if (!SYMFONY_DEBUG_G(debug_bt)) {
		return;
	}

	php_array_merge(Z_ARRVAL_P(return_value), Z_ARRVAL_P(SYMFONY_DEBUG_G(debug_bt)), 0 TSRMLS_CC);
}

PHP_FUNCTION(symfony_zval_info)
{
	zval *key = NULL, *arg = NULL;
	zval **data = NULL;
	HashTable *array = NULL;
	long options = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "zh|l", &key, &array, &options) == FAILURE) {
		return;
	}

	switch (Z_TYPE_P(key)) {
		case IS_STRING:
			if (zend_symtable_find(array, Z_STRVAL_P(key), Z_STRLEN_P(key) + 1, (void **)&data) == FAILURE) {
				return;
			}
		break;
		case IS_LONG:
			if (zend_hash_index_find(array, Z_LVAL_P(key), (void **)&data)) {
				return;
			}
		break;
	}

	arg = *data;

	array_init(return_value);

	add_assoc_string(return_value, "type", (char *)_symfony_debug_zval_type(arg), 1);
	add_assoc_stringl(return_value, "zval_hash", _symfony_debug_memory_address_hash((void *)arg TSRMLS_CC), 16, 0);
	add_assoc_long(return_value, "zval_refcount", Z_REFCOUNT_P(arg));
	add_assoc_bool(return_value, "zval_isref", (zend_bool)Z_ISREF_P(arg));

	if (Z_TYPE_P(arg) == IS_OBJECT) {
		char hash[33] = {0};

		php_spl_object_hash(arg, (char *)hash TSRMLS_CC);
		add_assoc_stringl(return_value, "object_class", (char *)Z_OBJCE_P(arg)->name, Z_OBJCE_P(arg)->name_length, 1);
		add_assoc_long(return_value, "object_refcount", EG(objects_store).object_buckets[Z_OBJ_HANDLE_P(arg)].bucket.obj.refcount);
		add_assoc_string(return_value, "object_hash", hash, 1);
		add_assoc_long(return_value, "object_handle", Z_OBJ_HANDLE_P(arg));
	} else if (Z_TYPE_P(arg) == IS_ARRAY) {
		add_assoc_long(return_value, "array_count", zend_hash_num_elements(Z_ARRVAL_P(arg)));
	} else if(Z_TYPE_P(arg) == IS_RESOURCE) {
		add_assoc_long(return_value, "resource_handle", Z_LVAL_P(arg));
		add_assoc_string(return_value, "resource_type", (char *)_symfony_debug_get_resource_type(Z_LVAL_P(arg) TSRMLS_CC), 1);
		add_assoc_long(return_value, "resource_refcount", _symfony_debug_get_resource_refcount(Z_LVAL_P(arg) TSRMLS_CC));
	} else if (Z_TYPE_P(arg) == IS_STRING) {
		add_assoc_long(return_value, "strlen", Z_STRLEN_P(arg));
	}
}

void symfony_debug_error_cb(int type, const char *error_filename, const uint error_lineno, const char *format, va_list args)
{
	TSRMLS_FETCH();
	zval *retval;

	switch (type) {
		case E_ERROR:
		case E_PARSE:
		case E_CORE_ERROR:
		case E_CORE_WARNING:
		case E_COMPILE_ERROR:
		case E_COMPILE_WARNING:
			ALLOC_INIT_ZVAL(retval);
#if IS_PHP_53
			zend_fetch_debug_backtrace(retval, 1, 0 TSRMLS_CC);
#else
			zend_fetch_debug_backtrace(retval, 1, 0, 0 TSRMLS_CC);
#endif
			SYMFONY_DEBUG_G(debug_bt) = retval;
	}

	SYMFONY_DEBUG_G(old_error_cb)(type, error_filename, error_lineno, format, args);
}

static const char* _symfony_debug_get_resource_type(long rsid TSRMLS_DC)
{
	const char *res_type;
	res_type = zend_rsrc_list_get_rsrc_type(rsid TSRMLS_CC);

	if (!res_type) {
		return "Unknown";
	}

	return res_type;
}

static int _symfony_debug_get_resource_refcount(long rsid TSRMLS_DC)
{
	zend_rsrc_list_entry *le;

	if (zend_hash_index_find(&EG(regular_list), rsid, (void **) &le)==SUCCESS) {
		return le->refcount;
	}

	return 0;
}

static char *_symfony_debug_memory_address_hash(void *address TSRMLS_DC)
{
	char *result = NULL;
	intptr_t address_rand;

	if (!SYMFONY_DEBUG_G(req_rand_init)) {
		if (!BG(mt_rand_is_seeded)) {
			php_mt_srand(GENERATE_SEED() TSRMLS_CC);
		}
		SYMFONY_DEBUG_G(req_rand_init) = (intptr_t)php_mt_rand(TSRMLS_C);
	}

	address_rand = (intptr_t)address ^ SYMFONY_DEBUG_G(req_rand_init);

	spprintf(&result, 17, "%016zx", address_rand);

	return result;
}

static const char *_symfony_debug_zval_type(zval *zv)
{
	switch (Z_TYPE_P(zv)) {
		case IS_NULL:
			return "NULL";
			break;

		case IS_BOOL:
			return "boolean";
			break;

		case IS_LONG:
			return "integer";
			break;

		case IS_DOUBLE:
			return "double";
			break;

		case IS_STRING:
			return "string";
			break;

		case IS_ARRAY:
			return "array";
			break;

		case IS_OBJECT:
			return "object";

		case IS_RESOURCE:
			return "resource";

		default:
			return "unknown type";
	}
}

zend_module_entry symfony_debug_module_entry = {
	STANDARD_MODULE_HEADER,
	"symfony_debug",
	symfony_debug_functions,
	PHP_MINIT(symfony_debug),
	PHP_MSHUTDOWN(symfony_debug),
	PHP_RINIT(symfony_debug),
	PHP_RSHUTDOWN(symfony_debug),
	PHP_MINFO(symfony_debug),
	PHP_SYMFONY_DEBUG_VERSION,
	PHP_MODULE_GLOBALS(symfony_debug),
	PHP_GINIT(symfony_debug),
	PHP_GSHUTDOWN(symfony_debug),
	NULL,
	STANDARD_MODULE_PROPERTIES_EX
};

#ifdef COMPILE_DL_SYMFONY_DEBUG
ZEND_GET_MODULE(symfony_debug)
#endif

PHP_GINIT_FUNCTION(symfony_debug)
{
	memset(symfony_debug_globals, 0 , sizeof(*symfony_debug_globals));
}

PHP_GSHUTDOWN_FUNCTION(symfony_debug)
{

}

PHP_MINIT_FUNCTION(symfony_debug)
{
	SYMFONY_DEBUG_G(old_error_cb) = zend_error_cb;
	zend_error_cb                 = symfony_debug_error_cb;

	return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(symfony_debug)
{
	zend_error_cb = SYMFONY_DEBUG_G(old_error_cb);

	return SUCCESS;
}

PHP_RINIT_FUNCTION(symfony_debug)
{
	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(symfony_debug)
{
	return SUCCESS;
}

PHP_MINFO_FUNCTION(symfony_debug)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "Symfony Debug support", "enabled");
	php_info_print_table_header(2, "Symfony Debug version", PHP_SYMFONY_DEBUG_VERSION);
	php_info_print_table_end();
}
