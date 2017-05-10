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

#ifndef SENSIOLABS_PHP_COMPAT_H_
#define SENSIOLABS_PHP_COMPAT_H_

#ifdef HAVE_SYS_TYPES_H
#include <sys/types.h>
#endif

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#if defined(HAVE_INTTYPES_H)
#include <inttypes.h>
#elif defined(HAVE_STDINT_H)
#include <stdint.h>
#endif

#ifdef PHP_WIN32
# include "win32/php_stdint.h"
#else
# ifndef HAVE_INT32_T
#  if SIZEOF_INT == 4
typedef int int32_t;
#  elif SIZEOF_LONG == 4
typedef long int int32_t;
#  endif
# endif

# ifndef HAVE_UINT32_T
#  if SIZEOF_INT == 4
typedef unsigned int uint32_t;
#  elif SIZEOF_LONG == 4
typedef unsigned long int uint32_t;
#  endif
# endif
#endif

#include "Zend/zend_extensions.h" /* for ZEND_EXTENSION_API_NO */
#include "Zend/zend_execute.h" /* for temp_variable */
#include "Zend/zend_compile.h"
#include "TSRM.h"

#define PHP_5_0_X_API_NO		220040412
#define PHP_5_1_X_API_NO		220051025
#define PHP_5_2_X_API_NO		220060519
#define PHP_5_3_X_API_NO		220090626
#define PHP_5_4_X_API_NO		220100525
#define PHP_5_5_X_API_NO		220121212
#define PHP_5_6_X_API_NO		220131226

#define IS_PHP_56 ZEND_EXTENSION_API_NO == PHP_5_6_X_API_NO
#define IS_AT_LEAST_PHP_56 ZEND_EXTENSION_API_NO >= PHP_5_6_X_API_NO

#define IS_PHP_55 ZEND_EXTENSION_API_NO == PHP_5_5_X_API_NO
#define IS_AT_LEAST_PHP_55 ZEND_EXTENSION_API_NO >= PHP_5_5_X_API_NO

#define IS_PHP_54 ZEND_EXTENSION_API_NO == PHP_5_4_X_API_NO
#define IS_AT_LEAST_PHP_54 ZEND_EXTENSION_API_NO >= PHP_5_4_X_API_NO

#define IS_PHP_53 ZEND_EXTENSION_API_NO == PHP_5_3_X_API_NO
#define IS_AT_LEAST_PHP_53 ZEND_EXTENSION_API_NO >= PHP_5_3_X_API_NO

#ifndef ZEND_EXT_API
# if WIN32|WINNT
#  define ZEND_EXT_API __declspec(dllexport)
# elif defined(__GNUC__) && __GNUC__ >= 4
#  define ZEND_EXT_API __attribute__ ((visibility("default")))
# else
#  define ZEND_EXT_API
# endif
#endif

#if IS_PHP_53

#ifndef PHP_FE_END /* PHP <= 5.3.2 */
#define ZEND_FE_END { NULL, NULL, NULL, 0, 0 }
#define PHP_FE_END ZEND_FE_END
#define ZEND_MOD_END { NULL, NULL, NULL, 0 }
#endif

// not defined because introduced in PHP 5.3.4
#ifndef zval_copy_property_ctor
#ifdef ZTS
extern void zval_property_ctor(zval **);
# define zval_shared_property_ctor zval_property_ctor
#else
# define zval_shared_property_ctor zval_add_ref
#endif
# define zval_copy_property_ctor(ce) ((copy_ctor_func_t) (((ce)->type == ZEND_INTERNAL_CLASS) ? zval_shared_property_ctor : zval_add_ref))
#endif

#define object_properties_init(obj, ce) do { \
		 zend_hash_copy(obj->properties, &ce->default_properties, zval_copy_property_ctor(ce), NULL, sizeof(zval *)); \
		} while (0);

typedef struct _zend_literal {
	zval       constant;
	zend_ulong hash_value;
	zend_uint  cache_slot;
} zend_literal;

#define GET_CLASS_FILENAME(class) (class)->filename

#else
#define GET_CLASS_FILENAME(class) (class)->info.user.filename
#endif

#define ZEND_OBJ_INIT(obj, ce) do { \
		zend_object_std_init(obj, ce TSRMLS_CC); \
		object_properties_init((obj), (ce)); \
	} while(0);

#if IS_PHP_53 || IS_PHP_54
static void zend_hash_get_current_key_zval_ex(const HashTable *ht, zval *key, HashPosition *pos)
{
	Bucket *p;

	p = pos ? (*pos) : ht->pInternalPointer;

	if (!p) {
		Z_TYPE_P(key) = IS_NULL;
	} else if (p->nKeyLength) {
		Z_TYPE_P(key) = IS_STRING;
		Z_STRVAL_P(key) = estrndup(p->arKey, p->nKeyLength - 1);
		Z_STRLEN_P(key) = p->nKeyLength - 1;
	} else {
		Z_TYPE_P(key) = IS_LONG;
		Z_LVAL_P(key) = p->h;
	}
}

static zend_always_inline int zend_vm_stack_get_args_count_ex(zend_execute_data *ex)
{
	if (ex) {
		void **p = ex->function_state.arguments;
		return (int)(zend_uintptr_t) *p;
	} else {
		return 0;
	}
}

static zend_always_inline zval** zend_vm_stack_get_arg_ex(zend_execute_data *ex, int requested_arg)
{
	void **p = ex->function_state.arguments;
	int arg_count = (int)(zend_uintptr_t) *p;

	if (UNEXPECTED(requested_arg > arg_count)) {
		return NULL;
	}
	return (zval**)p - arg_count + requested_arg - 1;
}
#endif

#if IS_AT_LEAST_PHP_55
#define SO_EX_CV(i)     (*EX_CV_NUM(execute_data, i))
#define SO_EX_T(offset) (*EX_TMP_VAR(execute_data, offset))
#define ZEND_GET_OP_TYPE(opline, opnum) opline->opnum##_type
#define ZEND_GET_OP_VAR(opline, opnum) SO_EX_T(opline->opnum.var)
#define ZEND_GET_NODE_CONST(znode) znode.zv

#define ZEND_GET_ZVAL_PTR(opnum, execute_data, res) do { \
	const znode_op *node = &execute_data->opline->opnum; \
\
	switch (ZEND_GET_OP_TYPE(execute_data->opline, opnum)) { \
			case IS_CONST: \
				res = node->zv; \
				break; \
			case IS_TMP_VAR: \
				res = &SO_EX_T(node->var).tmp_var; \
				break; \
			case IS_VAR: \
				res = SO_EX_T(node->var).var.ptr; \
				break; \
			case IS_CV: { \
				zval **tmp = SO_EX_CV(node->var); /* We are assuming BP_VAR_RW or BP_VAR_W here */ \
				res = tmp ? *tmp : NULL; \
				break; \
			} \
			default: \
				res = NULL; \
		} \
} while (0);

#define ZEND_GET_ZVAL_PTR_PTR(opnum, execute_data, res) do { \
	const znode_op *node = &execute_data->opline->opnum; \
	\
	switch (ZEND_GET_OP_TYPE(execute_data->opline, opnum)) { \
			case IS_VAR: \
				res = SO_EX_T(node->var).var.ptr_ptr; \
				break; \
			case IS_CV: { \
				zval **tmp = SO_EX_CV(node->var); /* We are assuming BP_VAR_RW or BP_VAR_W here */ \
				res = tmp ? tmp : NULL; \
				break; \
			} \
			default: \
				res = NULL; \
	} \
} while (0);

#elif IS_PHP_54
#define SO_EX_CV(i)     EG(current_execute_data)->CVs[(i)]
#define SO_EX_T(offset) (*(temp_variable *) ((char *) execute_data->Ts + offset))
#define ZEND_GET_OP_TYPE(opline, opnum) opline->opnum##_type
#define ZEND_GET_OP_VAR(opline, opnum) SO_EX_T(opline->opnum.var)
#define ZEND_GET_NODE_CONST(znode) znode.zv

#define ZEND_GET_ZVAL_PTR(opnum, execute_data, result) do { \
	const znode_op *node    = &execute_data->opline->opnum; \
	const temp_variable *Ts = execute_data->Ts; \
	\
	switch (ZEND_GET_OP_TYPE(execute_data->opline, opnum)) { \
			case IS_CONST: \
				result = node->zv; \
				break; \
			case IS_TMP_VAR: \
				result = &SO_EX_T(node->var).tmp_var; \
				break; \
			case IS_VAR: \
				result = SO_EX_T(node->var).var.ptr; \
				break; \
			case IS_CV: { \
				zval **tmp = SO_EX_CV(node->var); \
				result = tmp ? *tmp : NULL; \
				break; \
			} \
			default: \
				result = NULL; \
		} \
} while (0);

#define ZEND_GET_ZVAL_PTR_PTR(opnum, execute_data, result) do { \
	const znode_op *node    = &execute_data->opline->opnum; \
	const temp_variable *Ts = execute_data->Ts; \
	\
	switch (ZEND_GET_OP_TYPE(execute_data->opline, opnum)) { \
		case IS_VAR: \
			result = SO_EX_T(node->var).var.ptr_ptr; \
			break; \
		case IS_CV: { \
			zval **tmp = SO_EX_CV(node->var); /* We are assuming BP_VAR_RW or BP_VAR_W here */ \
			result = tmp ? tmp : NULL; \
			break; \
		} \
		default: \
			result = NULL; \
	} \
} while (0);

#else /* PHP 5.3 */
#define SO_EX_CV(i)     EG(current_execute_data)->CVs[(i)]
#define SO_EX_T(offset) (*(temp_variable *) ((char *) execute_data->Ts + offset))
#define ZEND_GET_OP_TYPE(opline, opnum) opline->opnum.op_type
#define ZEND_GET_OP_VAR(opline, opnum) SO_EX_T(opline->opnum.u.var)
#define ZEND_GET_NODE_CONST(znode) &znode.u.constant

#define ZEND_GET_ZVAL_PTR(opnum, execute_data, result) do { \
	znode *node = &execute_data->opline->opnum; \
	\
	switch (ZEND_GET_OP_TYPE(execute_data->opline, opnum)) { \
			case IS_CONST: \
				result = &node->u.constant; \
				 break; \
			case IS_TMP_VAR: \
				result = &SO_EX_T(node->u.var).tmp_var; \
				break; \
			case IS_VAR: \
				result = SO_EX_T(node->u.var).var.ptr; \
				break; \
			case IS_CV: { \
				zval **tmp = SO_EX_CV(node->u.var); \
				result = tmp ? *tmp : NULL; \
				break; \
			} \
			default: \
				result = NULL; \
		} \
} while (0);

#define ZEND_GET_ZVAL_PTR_PTR(opnum, execute_data, result) do { \
	znode *node = &execute_data->opline->opnum; \
	\
	switch (ZEND_GET_OP_TYPE(execute_data->opline, opnum)) { \
		case IS_VAR: \
			result = SO_EX_T(node->u.var).var.ptr_ptr; \
			break; \
		case IS_CV: { \
			zval **tmp = SO_EX_CV(node->u.var); /* We are assuming BP_VAR_RW or BP_VAR_W here */ \
			result = tmp ? tmp : NULL; \
			break; \
		} \
		default: \
			result = NULL; \
	} \
} while (0);

#endif

#endif /* SENSIOLABS_PHP_COMPAT_H_ */
