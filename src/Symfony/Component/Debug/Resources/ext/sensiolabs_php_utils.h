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

#ifndef SENSIOLABS_PHP_UTILS_H_
#define SENSIOLABS_PHP_UTILS_H_

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#define STRINGIFY(x) #x
#define XSTRINGIFY(a) STRINGIFY(a)

#define FETCH_OBJECT(type, name) type *name = (type *)zend_object_store_get_object(getThis() TSRMLS_CC);

#define ZEND_HASH_ITERATE_START(ht, data) { \
	HashPosition pos; \
	for(zend_hash_internal_pointer_reset_ex((ht), &pos); \
		zend_hash_get_current_data_ex((ht), (void **)&(data), &pos) == SUCCESS; \
		zend_hash_move_forward_ex((ht), &pos)) {
#define ZEND_HASH_ITERATE_END }}
#define ZEND_HASH_ITERATE_SKIP continue;

#define THIS(var) zend_read_property(Z_OBJCE_P(getThis()), getThis(), (var), sizeof((var))-1, 0 TSRMLS_CC)

#define RETURN_THIS RETURN_ZVAL(getThis(), 1, 0)

#define FETCH_AUTO_GLOBAL(var) zval *_##var = NULL; zend_is_auto_global(STRINGIFY(_##var), sizeof(STRINGIFY(_##var)) - 1 TSRMLS_CC); _##var = PG(http_globals)[TRACK_VARS_##var];

#define PHP_MINIT_PROXY_CALL(module_name) PHP_MINIT(module_name)(INIT_FUNC_ARGS_PASSTHRU);
#define PHP_MSHUTDOWN_PROXY_CALL(module_name) PHP_MSHUTDOWN(module_name)(SHUTDOWN_FUNC_ARGS_PASSTHRU);

#ifdef ZTS
#define ZEND_INI_MH_BASE_DECL char *base; base = (char *) ts_resource(*((int *) mh_arg2));
#define ZEND_DESTROY_MODULE_GLOBALS(module_name, globals_dtor)
#define ZEND_MODULE_GLOBALS_DTOR_PROXY_CALL(module) ZEND_MODULE_GLOBALS_DTOR_N(module)\
	( (zend_##module##_globals*) (*((void ***) tsrm_ls))[TSRM_UNSHUFFLE_RSRC_ID(module##_globals_id)] TSRMLS_CC);
#else
#define ZEND_INI_MH_BASE_DECL char *base; base = (char *) mh_arg2;
#define ZEND_DESTROY_MODULE_GLOBALS(module_name, globals_dtor)	globals_dtor(&module_name##_globals);
#define ZEND_MODULE_GLOBALS_DTOR_PROXY_CALL(module) ZEND_MODULE_GLOBALS_DTOR_N(module)(&module##_globals TSRMLS_CC);
#endif

#define zend_ptr_stack_clean_and_destroy(stack, dtor, free) zend_ptr_stack_clean(stack, dtor, free); zend_ptr_stack_destroy(stack);

#endif /* SENSIOLABS_PHP_UTILS_H_ */
