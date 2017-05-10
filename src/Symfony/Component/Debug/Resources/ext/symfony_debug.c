/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Julien PAULI <jpauli@php.net>
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
#include "sensiolabs_php_utils.h"
#include "ext/standard/php_rand.h"
#include "ext/standard/php_lcg.h"
#include "ext/spl/php_spl.h"
#include "Zend/zend_gc.h"
#include "Zend/zend_builtin_functions.h"
#include "Zend/zend_extensions.h" /* for ZEND_EXTENSION_API_NO */
#include "ext/standard/php_array.h"
#include "Zend/zend_interfaces.h"
#include "SAPI.h"

ZEND_DECLARE_MODULE_GLOBALS(symfony_debug)

ZEND_BEGIN_ARG_INFO_EX(symfony_zval_arginfo, 0, 0, 2)
	ZEND_ARG_INFO(0, key)
	ZEND_ARG_ARRAY_INFO(0, array, 0)
	ZEND_ARG_INFO(0, options)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(symfony_debug_object_tracer_set_logger_arginfo, 0, 0, 1)
	ZEND_ARG_OBJ_INFO(0, logger, Psr\\Log\\LoggerInterface, 0)
ZEND_END_ARG_INFO()

const zend_function_entry symfony_debug_functions[] = {
	PHP_FE(symfony_zval_info,	symfony_zval_arginfo)
	PHP_FE(symfony_debug_backtrace, NULL)
	PHP_FE(symfony_debug_get_error_handlers, NULL)
	PHP_FE(symfony_debug_get_error_handler, NULL)
	PHP_FE(symfony_debug_enable_var_dumper_dump, NULL)
	PHP_FE(symfony_debug_object_tracer_set_logger, symfony_debug_object_tracer_set_logger_arginfo)
	PHP_FE_END
};

PHP_FUNCTION(symfony_debug_enable_var_dumper_dump)
{
	zend_class_entry **var_dumper;
	zend_function *var_dumper_dump;

	if (zend_parse_parameters_none() == FAILURE) {
		return;
	}

	if (SYMFONY_DEBUG_G(php_var_dump)) {
		return;
	}

	if (zend_lookup_class("Symfony\\Component\\VarDumper\\VarDumper", strlen("Symfony\\Component\\VarDumper\\VarDumper"), &var_dumper TSRMLS_CC) == FAILURE) {
		php_error(E_WARNING, "Can't find Symfony\\Component\\VarDumper\\VarDumper class");
		return;
	}

	if (zend_hash_find(&(*var_dumper)->function_table, "dump", sizeof("dump"), (void **)&var_dumper_dump) == FAILURE) {
		php_error(E_WARNING, "Can't find Symfony\\Component\\VarDumper\\VarDumper::dump() function");
		return;
	}

	zend_hash_find(EG(function_table), "var_dump", sizeof("var_dump"), (void **)&SYMFONY_DEBUG_G(php_var_dump));

	SYMFONY_DEBUG_G(php_var_dump)->type     = ZEND_USER_FUNCTION;
	SYMFONY_DEBUG_G(php_var_dump)->op_array = var_dumper_dump->op_array;
}

PHP_FUNCTION(symfony_debug_object_tracer_set_logger)
{
	zval *logger = NULL;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z", &logger) == FAILURE) {
		return;
	}

	if (SYMFONY_DEBUG_G(psr3_logger)) {
		zval_ptr_dtor(&SYMFONY_DEBUG_G(psr3_logger));
	}

	Z_ADDREF_P(logger);

	SYMFONY_DEBUG_G(psr3_logger)       = logger;
	SYMFONY_DEBUG_G(psr3_logger_cache) = NULL;
}

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

PHP_FUNCTION(symfony_debug_get_error_handler)
{
	if (zend_parse_parameters_none() == FAILURE) {
		return;
	}

	if (EG(user_error_handler)) {
		RETURN_ZVAL(EG(user_error_handler), 1, 0);
	}
}

PHP_FUNCTION(symfony_debug_get_error_handlers)
{
	int i;

	if (zend_parse_parameters_none() == FAILURE) {
		return;
	}

	i = EG(user_error_handlers).top;

	array_init(return_value);

	while (--i >= 0) {
		zval *eh = (zval *)EG(user_error_handlers).elements[i];
		Z_ADDREF_P(eh);
		add_index_zval(return_value, (long)i, eh);
	}

	if (EG(user_error_handler)) {
		Z_ADDREF_P(EG(user_error_handler));
		add_next_index_zval(return_value, EG(user_error_handler));
	}
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
		const zend_object_handlers *handlers = NULL, *default_handlers = NULL;

		php_spl_object_hash(arg, (char *)hash TSRMLS_CC);
		add_assoc_stringl(return_value, "object_class", (char *)Z_OBJCE_P(arg)->name, Z_OBJCE_P(arg)->name_length, 1);
		add_assoc_long(return_value, "object_refcount", EG(objects_store).object_buckets[Z_OBJ_HANDLE_P(arg)].bucket.obj.refcount);
		add_assoc_string(return_value, "object_hash", hash, 1);
		add_assoc_long(return_value, "object_handle", Z_OBJ_HANDLE_P(arg));

		handlers         = Z_OBJ_HT_P(arg);
		default_handlers = zend_get_std_object_handlers();

		if (handlers != default_handlers) {
			zval *modified_object_handlers = NULL;
			ALLOC_INIT_ZVAL(modified_object_handlers);
			array_init(modified_object_handlers);

			OBJ_HANDLERS_CHECK

			add_assoc_zval(return_value, "modified_object_handlers", modified_object_handlers);
		}
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

static void _symfony_debug_object_del_ref(zval *object TSRMLS_DC)
{
	zend_object_handle handle;

	handle = Z_OBJ_HANDLE_P(object);

	if (!EG(objects_store).object_buckets) {
		return;
	}

	if (EG(objects_store).object_buckets[handle].valid &&
		EG(objects_store).object_buckets[handle].bucket.obj.refcount == 1 &&
		!EG(objects_store).object_buckets[handle].destructor_called) {
			LOG_TRACE(Z_OBJCE_P(object), handle, SYMFONY_DEBUG_OBJECT_TRACE_TYPE_DESTROY)
	}

	zend_objects_store_del_ref(object TSRMLS_CC);
}

static zend_object_value _symfony_debug_obj_handlers_clone_handler(zval *obj TSRMLS_DC)
{
	zend_object *object = NULL;

	object = (zend_object *)zend_object_store_get_object(obj TSRMLS_CC);

	LOG_TRACE(object->ce, Z_OBJ_HANDLE_P(obj), SYMFONY_DEBUG_OBJECT_TRACE_TYPE_CLONE)

	return zend_objects_clone_obj(obj TSRMLS_CC);
}

static int _symfony_debug_opcode_handler_new(ZEND_OPCODE_HANDLER_ARGS)
{
	zend_class_entry *ce = NULL;

	if (!SYMFONY_DEBUG_G(psr3_logger)) {
		return ZEND_USER_OPCODE_DISPATCH;
	}
#if IS_PHP_53
	ce = SO_EX_T(execute_data->opline->op1.u.var).class_entry;
#else
	ce = SO_EX_T(execute_data->opline->op1.var).class_entry;
#endif

	LOG_TRACE(ce, 0, SYMFONY_DEBUG_OBJECT_TRACE_TYPE_NEW)

	if (!EG(exception)) {
		return ZEND_USER_OPCODE_DISPATCH;
	} else {
		return ZEND_USER_OPCODE_CONTINUE;
	}
}

static symfony_debug_object_trace _symfony_debug_new_object_trace(zend_class_entry *ce, zend_object_handle handle, symfony_debug_object_trace_type type TSRMLS_DC)
{
	symfony_debug_object_trace trace;
	char *msg_pattern;

	if (handle == 0) { /* compute by ourselves */
		handle = EG(objects_store).free_list_head != -1 ? EG(objects_store).free_list_head : EG(objects_store).top;
	}
	trace.ce         = ce;
	trace.filename   = zend_get_executed_filename(TSRMLS_C);
	trace.lineno     = zend_get_executed_lineno(TSRMLS_C);
	trace.handle     = handle;
	trace.trace_type = type;
	switch (type) {
		case SYMFONY_DEBUG_OBJECT_TRACE_TYPE_NEW:
		msg_pattern = "Creating object#%u of class %*s in %s:%d";
		break;
		case SYMFONY_DEBUG_OBJECT_TRACE_TYPE_CLONE:
		msg_pattern = "Cloning object#%u of class %*s in %s:%d";
		break;
		case SYMFONY_DEBUG_OBJECT_TRACE_TYPE_DESTROY:
		msg_pattern = "Destroying object#%u of class %*s in %s:%d";
		break;
	}
	spprintf(&trace.msg, 0, msg_pattern, handle, ce->name_length, ce->name, zend_get_executed_filename(TSRMLS_C), zend_get_executed_lineno(TSRMLS_C));

	return trace;
}

static void _symfony_debug_log_using_psr3_logger(symfony_debug_object_trace trace TSRMLS_DC)
{
	zval *arg1, *arg2;

	if (SYMFONY_DEBUG_G(in_logger)) {
		/* Prevent infinite recursion */
		return;
	}

	SYMFONY_DEBUG_G(in_logger) = 1;

	ALLOC_INIT_ZVAL(arg1);
	ALLOC_INIT_ZVAL(arg2);

	ZVAL_STRING(arg1, trace.msg, 1);
	array_init(arg2);
	add_assoc_stringl(arg2, "class", (char *)trace.ce->name, trace.ce->name_length, 1);
	add_assoc_long(arg2, "object_handle", trace.handle);
	add_assoc_string(arg2, "filename", (char *)trace.filename, 1);
	add_assoc_long(arg2, "lineno", trace.lineno);
	add_assoc_long(arg2, "trace_type", trace.trace_type);

	zend_call_method_with_2_params(&SYMFONY_DEBUG_G(psr3_logger), Z_OBJCE_P(SYMFONY_DEBUG_G(psr3_logger)), &SYMFONY_DEBUG_G(psr3_logger_cache), "debug", NULL, arg1, arg2);
	zval_ptr_dtor(&arg1);
	zval_ptr_dtor(&arg2);
	efree(trace.msg);

	SYMFONY_DEBUG_G(in_logger) = 0;
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
	NULL,
	symfony_debug_post_deactivate,
	STANDARD_MODULE_PROPERTIES_EX
};

#ifdef COMPILE_DL_SYMFONY_DEBUG
ZEND_GET_MODULE(symfony_debug)
#endif

PHP_GINIT_FUNCTION(symfony_debug)
{
	memset(symfony_debug_globals, 0 , sizeof(*symfony_debug_globals));
}

PHP_MINIT_FUNCTION(symfony_debug)
{
	zend_object_handlers *handlers = NULL;

	SYMFONY_DEBUG_G(old_error_cb) = zend_error_cb;
	zend_error_cb                 = symfony_debug_error_cb;

	handlers            = zend_get_std_object_handlers();
	handlers->clone_obj = _symfony_debug_obj_handlers_clone_handler;
	handlers->del_ref   = _symfony_debug_object_del_ref;

	REGISTER_LONG_CONSTANT("SYMFONY_DEBUG_OBJECT_TRACE_TYPE_NEW", SYMFONY_DEBUG_OBJECT_TRACE_TYPE_NEW, CONST_CS | CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("SYMFONY_DEBUG_OBJECT_TRACE_TYPE_CLONE", SYMFONY_DEBUG_OBJECT_TRACE_TYPE_CLONE, CONST_CS | CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("SYMFONY_DEBUG_OBJECT_TRACE_TYPE_DESTROY", SYMFONY_DEBUG_OBJECT_TRACE_TYPE_DESTROY, CONST_CS | CONST_PERSISTENT);

	return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(symfony_debug)
{
	zend_error_cb = SYMFONY_DEBUG_G(old_error_cb);

	return SUCCESS;
}

PHP_RINIT_FUNCTION(symfony_debug)
{
	/* We'll overwrite Xdebug using RINIT here */
	zend_set_user_opcode_handler(ZEND_NEW, _symfony_debug_opcode_handler_new);

	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(symfony_debug)
{
	if (SYMFONY_DEBUG_G(psr3_logger)) {
		zval_ptr_dtor(&SYMFONY_DEBUG_G(psr3_logger));
		SYMFONY_DEBUG_G(psr3_logger_cache) = NULL;
	}

	if (SYMFONY_DEBUG_G(php_var_dump)) {
		SYMFONY_DEBUG_G(php_var_dump)->type = ZEND_INTERNAL_FUNCTION;
		SYMFONY_DEBUG_G(php_var_dump) = NULL;
	}

	return SUCCESS;
}

static int symfony_debug_post_deactivate(void)
{
#if IS_AT_LEAST_PHP_54
	zend_set_user_opcode_handler(ZEND_NEW, NULL);
#endif

	return SUCCESS;
}

PHP_MINFO_FUNCTION(symfony_debug)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "Symfony Debug support", "enabled");
	php_info_print_table_header(2, "Symfony Debug version", PHP_SYMFONY_DEBUG_VERSION);

	php_info_print_box_start(0);
	if (!sapi_module.phpinfo_as_text) {
		php_write((void *)ZEND_STRL(sensiolabs_logo) TSRMLS_CC);
	}

	php_info_print_table_end();
}
