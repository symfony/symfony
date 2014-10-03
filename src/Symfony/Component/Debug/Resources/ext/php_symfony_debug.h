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

#ifndef PHP_SYMFONY_DEBUG_H
#define PHP_SYMFONY_DEBUG_H

#include "sensiolabs_php_compat.h"

extern zend_module_entry symfony_debug_module_entry;
#define phpext_symfony_debug_ptr &symfony_debug_module_entry
#ifdef COMPILE_DL_SYMFONY_DEBUG
zend_module_entry *get_module(void);
#endif

#define PHP_SYMFONY_DEBUG_VERSION "2.7"

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
	void (*old_error_cb)(int type, const char *error_filename, const uint error_lineno, const char *format, va_list args);
	zval *debug_bt;
	zval *psr3_logger;
	zend_function *psr3_logger_cache;
	zend_function *php_var_dump;
	intptr_t req_rand_init;
	zend_bool in_logger;
ZEND_END_MODULE_GLOBALS(symfony_debug)

PHP_MINIT_FUNCTION(symfony_debug);
PHP_MSHUTDOWN_FUNCTION(symfony_debug);
PHP_RINIT_FUNCTION(symfony_debug);
PHP_RSHUTDOWN_FUNCTION(symfony_debug);
PHP_MINFO_FUNCTION(symfony_debug);
PHP_GINIT_FUNCTION(symfony_debug);
PHP_GSHUTDOWN_FUNCTION(symfony_debug);

PHP_FUNCTION(symfony_zval_info);
PHP_FUNCTION(symfony_debug_backtrace);
PHP_FUNCTION(symfony_debug_object_tracer_set_logger);
PHP_FUNCTION(symfony_debug_get_error_handlers);
PHP_FUNCTION(symfony_debug_get_error_handler);
PHP_FUNCTION(symfony_debug_enable_var_dumper_dump);

static char *_symfony_debug_memory_address_hash(void * TSRMLS_DC);
static const char *_symfony_debug_zval_type(zval *);
static const char* _symfony_debug_get_resource_type(long TSRMLS_DC);
static int _symfony_debug_get_resource_refcount(long TSRMLS_DC);
static int symfony_debug_post_deactivate(void);
static const char sensiolabs_logo[] = "<img src=\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAHYAAAAUCAMAAABvRTlyAAAAz1BMVEUAAAAAAAAAAAAsThWB5j4AAACD6T8AAACC6D+C6D6C6D+C6D4AAAAAAACC6D4AAAAAAACC6D8AAAAAAAAAAAAAAAAAAAAAAACC6D4AAAAAAAAAAACC6D4AAAAAAAAAAAAAAAAAAAAAAACC6D8AAACC6D4AAAAAAAAAAAAAAAAAAACC6D8AAACC6D6C6D+B6D+C6D+C6D+C6D8AAACC6D6C6D4AAACC6D/K/2KC6D+B6D6C6D6C6D+C6D8sTxUyWRhEeiEAAACC6D+C5z6B6D7drnEVAAAAQXRSTlMAE3oCNSUuDHFHzxaF9UFsu+irX+zlKzYimaJXktyOSFD6BolxqT7QGMMdarMIpuO28r9EolXKgR16OphfXYd4V14GtB4AAAMpSURBVEjHvVSJctowEF1jjME2RziMwUCoMfd9heZqG4n//6buLpJjkmYm03byZmxJa2nf6u2uQcG2bfhqRN4LoTKBzyGDm68M7mAwcOEdjo4zhA/Rf9Go/CVtTgiRhXfIC3EDH8F/eUX1/9KexRo+QgOdtHDsEe/sM7QT32/+K61Z1LFXcXJxN4pTbu1aTQUzuy2PIA0rDo0/0Aa5XFaJvKaVTrubywXvaa1Wq4Vu/Snr3Y7Aojh4VccwykW2N2oQ8wmjyut6+Q1t5ywIG5Npj1sh5E0B7YOzFDjfuRfaOh3O+MbbVNfTWS9COZk3Obd2su5d0a6IU9KLREbw8gEehWSr1r2sPWciXLG38r5NdW0xu9eioU87omjC9yNaMi5GNf6WppVSOqXCFkmCvMB3p9SROLoYQn5pDgQOujA1xjYvqH+plUdkwnmII8VxR/PKYkrfLLomhVlE3b/LhNbNr7hp0H2JaOc4v8dFB58HSsFTSafaqtY1sT3GO8wsy5rhokYPlRJdjPMajyYqTt1EHF/2uqSWQWmAjCUSmQ1MS3g8Btf1XOsy7YIC0CB1b5Xw1Vhba0zbxiCAQLH9TNPmHJXQUtJAN0KcDsoqLxsNvJrJExa7mKIdp2lRE2WexiS4pqWk/0jROlw6K6bV9YOBDGAuqMJ0bnuUKGB0L27bxgRhGEbzihbhxxXaQC88Vkwq8ldCi86RApWUb0Q+4VDosBCc+1s81lUdnBavH4Zp2mm3O44USwOfvSo9oBiwpFg71lMS1VKJLKljS3j9p+fOTvXXlsSNuEv6YPaZda9uRope0VJfKdo7fPiYfSmvFjXQbkhY0d9hCbBWIktRgEDieDhf1N3wbbkmNNgRy8hyl620yGQat/grV3HMpc2HDKTVmOPFz6ylPCKt/nXcAyV260jaAowwIW0YuBzrOgb/KrddZS9OmJaLgpWK4JX2DDuklcLZSDGcn8Vmx9YDNvT6UsjyBApRyFQVX7Vxm9TGxE16nmfRd8/zQoDmggQOTRh5Hv8pMt9Q/L2JmSwkMCE7dA4BuDjHJwfu0Om4QAhOjrN5XkIatglfiN/bUPdCQFjTYgAAAABJRU5ErkJggg==\">";

void symfony_debug_error_cb(int type, const char *error_filename, const uint error_lineno, const char *format, va_list args);

typedef enum {
	SYMFONY_DEBUG_OBJECT_TRACE_TYPE_NEW,
	SYMFONY_DEBUG_OBJECT_TRACE_TYPE_CLONE,
	SYMFONY_DEBUG_OBJECT_TRACE_TYPE_DESTROY
} symfony_debug_object_trace_type;

typedef struct _symfony_debug_object_trace {
	zend_class_entry *ce;
	zend_object_handle handle;
	const char*filename;
	char *msg;
	uint lineno;
	symfony_debug_object_trace_type trace_type;
} symfony_debug_object_trace;

static symfony_debug_object_trace _symfony_debug_new_object_trace(zend_class_entry *ce, zend_object_handle handle, symfony_debug_object_trace_type type TSRMLS_DC);
static char *_symfony_debug_memory_address_hash(void * TSRMLS_DC);
static const char* _symfony_debug_get_resource_type(long TSRMLS_DC);
static int _symfony_debug_get_resource_refcount(long TSRMLS_DC);
static zend_object_value _symfony_debug_obj_handlers_clone_handler(zval *obj TSRMLS_DC);
static void _symfony_debug_log_using_psr3_logger(symfony_debug_object_trace trace TSRMLS_DC);
static int _symfony_debug_opcode_handler_new(ZEND_OPCODE_HANDLER_ARGS);

void symfony_debug_error_cb(int type, const char *error_filename, const uint error_lineno, const char *format, va_list args);

#define HANDLER_LIST_M(m) if(handlers->m != default_handlers->m) { add_next_index_string(modified_object_handlers, #m, 1); }

#if IS_AT_LEAST_PHP_54
#define OBJ_HANDLERS_CHECK \
	HANDLER_LIST_M(add_ref) \
	HANDLER_LIST_M(del_ref) \
	HANDLER_LIST_M(clone_obj) \
	HANDLER_LIST_M(read_property) \
	HANDLER_LIST_M(write_property) \
	HANDLER_LIST_M(read_dimension) \
	HANDLER_LIST_M(write_dimension) \
	HANDLER_LIST_M(get_property_ptr_ptr) \
	HANDLER_LIST_M(get) \
	HANDLER_LIST_M(set) \
	HANDLER_LIST_M(has_property) \
	HANDLER_LIST_M(unset_property) \
	HANDLER_LIST_M(has_dimension) \
	HANDLER_LIST_M(unset_dimension) \
	HANDLER_LIST_M(get_properties) \
	HANDLER_LIST_M(get_method) \
	HANDLER_LIST_M(call_method) \
	HANDLER_LIST_M(get_constructor) \
	HANDLER_LIST_M(get_class_entry) \
	HANDLER_LIST_M(get_class_name) \
	HANDLER_LIST_M(compare_objects) \
	HANDLER_LIST_M(cast_object) \
	HANDLER_LIST_M(count_elements) \
	HANDLER_LIST_M(get_debug_info) \
	HANDLER_LIST_M(get_closure) \
	HANDLER_LIST_M(get_gc)
#else
#define OBJ_HANDLERS_CHECK \
	HANDLER_LIST_M(add_ref) \
	HANDLER_LIST_M(del_ref) \
	HANDLER_LIST_M(clone_obj) \
	HANDLER_LIST_M(read_property) \
	HANDLER_LIST_M(write_property) \
	HANDLER_LIST_M(read_dimension) \
	HANDLER_LIST_M(write_dimension) \
	HANDLER_LIST_M(get_property_ptr_ptr) \
	HANDLER_LIST_M(get) \
	HANDLER_LIST_M(set) \
	HANDLER_LIST_M(has_property) \
	HANDLER_LIST_M(unset_property) \
	HANDLER_LIST_M(has_dimension) \
	HANDLER_LIST_M(unset_dimension) \
	HANDLER_LIST_M(get_properties) \
	HANDLER_LIST_M(get_method) \
	HANDLER_LIST_M(call_method) \
	HANDLER_LIST_M(get_constructor) \
	HANDLER_LIST_M(get_class_entry) \
	HANDLER_LIST_M(get_class_name) \
	HANDLER_LIST_M(compare_objects) \
	HANDLER_LIST_M(cast_object) \
	HANDLER_LIST_M(count_elements) \
	HANDLER_LIST_M(get_debug_info) \
	HANDLER_LIST_M(get_closure)
#endif

#define LOG_TRACE(class_entry, obj_handle, trace_type) do { \
	if (SYMFONY_DEBUG_G(psr3_logger)) { \
		symfony_debug_object_trace trace; \
		trace = _symfony_debug_new_object_trace((class_entry), (obj_handle), (trace_type) TSRMLS_CC); \
	\
		_symfony_debug_log_using_psr3_logger(trace TSRMLS_CC); \
	} \
} while (0);

#ifdef ZTS
#define SYMFONY_DEBUG_G(v) TSRMG(symfony_debug_globals_id, zend_symfony_debug_globals *, v)
#else
#define SYMFONY_DEBUG_G(v) (symfony_debug_globals.v)
#endif

#endif	/* PHP_SYMFONY_DEBUG_H */
