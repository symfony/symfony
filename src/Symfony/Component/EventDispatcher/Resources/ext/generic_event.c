/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#include "event_dispatcher_main.h"

zend_class_entry *generic_event_ce;
static zend_function_entry generic_event_methods[];

#define SUBJECT_PROPERTY "subject"
#define ARGUMENTS_PROPERTY "arguments"

PHP_MINIT_FUNCTION(generic_event_class)
{
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, ZEND_NS_NAME(EVENT_DISPATCHER_NS, "GenericEvent"), generic_event_methods);
    generic_event_ce = zend_register_internal_class_ex(&ce, event_ce, NULL TSRMLS_CC);
    zend_class_implements(generic_event_ce TSRMLS_CC, 2, zend_ce_arrayaccess, zend_ce_aggregate);
    zend_declare_property_null(generic_event_ce, SUBJECT_PROPERTY, sizeof(SUBJECT_PROPERTY)-1, ZEND_ACC_PROTECTED TSRMLS_CC);
    zend_declare_property_null(generic_event_ce, ARGUMENTS_PROPERTY, sizeof(ARGUMENTS_PROPERTY)-1, ZEND_ACC_PROTECTED TSRMLS_CC);

    return SUCCESS;
}

PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, __construct)
{
    zval *subject, *arguments;

    if (zend_parse_parameters(2 TSRMLS_CC, "za", &subject, &arguments) == FAILURE) {
        return;
    }

    zend_update_property(generic_event_ce, getThis(), SUBJECT_PROPERTY, sizeof(SUBJECT_PROPERTY)-1, subject TSRMLS_CC);
    zend_update_property(generic_event_ce, getThis(), ARGUMENTS_PROPERTY, sizeof(ARGUMENTS_PROPERTY)-1, arguments TSRMLS_CC);
}

PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, getSubject)
{
    zval *data;
    data = zend_read_property(generic_event_ce, getThis(), SUBJECT_PROPERTY, sizeof(SUBJECT_PROPERTY)-1, 1 TSRMLS_CC);

    RETURN_ZVAL(data, 1, 0);
}

PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, setArguments)
{
    zval *arr;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "a", &arr) == FAILURE) {
        return;
    }

    zend_update_property(generic_event_ce, getThis(), ARGUMENTS_PROPERTY, sizeof(ARGUMENTS_PROPERTY)-1, arr TSRMLS_CC);

    RETURN_ZVAL(getThis(), 1, 0);
}

PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, getArguments)
{
    zval *data;
    data = zend_read_property(generic_event_ce, getThis(), ARGUMENTS_PROPERTY, sizeof(ARGUMENTS_PROPERTY)-1, 1 TSRMLS_CC);

    RETURN_ZVAL(data, 1, 0);
}

PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, setArgument)
{
    zval *value, *arguments;
    char *key;
    int key_len = 0;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sz", &key, &key_len, &value) == FAILURE) {
        return;
    }

    arguments = THIS(ARGUMENTS_PROPERTY);
    Z_ADDREF_P(value);
    add_assoc_zval(arguments, key, value);

    RETURN_THIS;
}

PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, hasArgument)
{
    HashTable *arr_hash;
    char *key;
    int key_len = 0;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &key, &key_len) == FAILURE) {
        return;
    }

    arr_hash = Z_ARRVAL_P(THIS(ARGUMENTS_PROPERTY));

    if (zend_hash_exists(arr_hash, key, key_len + 1)) {
        RETURN_TRUE;
    }

    RETURN_FALSE;
}

PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, getArgument)
{
    zval **data = NULL;
    HashTable *arr_hash = NULL;
    char *key = NULL;
    int key_len = 0;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &key, &key_len) == FAILURE) {
        return;
    }

    arr_hash = Z_ARRVAL_P(THIS(ARGUMENTS_PROPERTY));

    if (zend_hash_find(arr_hash, key, key_len + 1, (void**) &data) == SUCCESS) {
        RETURN_ZVAL(*data, 1, 0);
    }

    FETCH_EVENT_OBJECT
    zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC, "%s not found in %#s", key, event->name.name_len, event->name.name);
}

PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, offsetUnset)
{
    HashTable *arr_hash;
    char *key;
    int key_len = 0;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &key, &key_len) == FAILURE) {
        return;
    }

    arr_hash = Z_ARRVAL_P(THIS(ARGUMENTS_PROPERTY));

    if (zend_hash_exists(arr_hash, key, key_len + 1)) {
        zend_hash_del(arr_hash, key, key_len + 1);
    }
}

PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, getIterator)
{
    zval c_ret, constructor, *argv[1];

    Z_TYPE_P(return_value) = IS_OBJECT;
    object_init_ex(return_value, spl_ce_ArrayIterator);

    INIT_ZVAL(c_ret);
    INIT_ZVAL(constructor);
    argv[0] = THIS(ARGUMENTS_PROPERTY);

    ZVAL_STRING(&constructor, ZEND_CONSTRUCTOR_FUNC_NAME, 0);
    if (call_user_function(NULL, &return_value, &constructor, &c_ret, 1, argv TSRMLS_CC) == FAILURE) {
        php_error_docref(NULL TSRMLS_CC, E_ERROR, "Error calling constructor");
    }

    zval_dtor(&c_ret);
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_GenericEvent_construct, 0, 0, 0)
    ZEND_ARG_INFO(0, subject)
    ZEND_ARG_ARRAY_INFO(0, arguments, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_GenericEvent_getArgument, 0, 0, 1)
    ZEND_ARG_INFO(0, key)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_GenericEvent_setArgument, 0, 0, 2)
    ZEND_ARG_INFO(0, key)
    ZEND_ARG_INFO(0, value)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_GenericEvent_setArguments, 0, 0, 0)
    ZEND_ARG_ARRAY_INFO(0, arguments, 0)
ZEND_END_ARG_INFO()

static zend_function_entry generic_event_methods[] = {
    PHP_ME(Symfony_Component_EventDispatcher_GenericEvent,  __construct,  arginfo_GenericEvent_construct, ZEND_ACC_PUBLIC|ZEND_ACC_CTOR)
    PHP_ME(Symfony_Component_EventDispatcher_GenericEvent,  getSubject,   NULL, ZEND_ACC_PUBLIC)

    PHP_ME(Symfony_Component_EventDispatcher_GenericEvent,  setArguments, arginfo_GenericEvent_setArguments, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_GenericEvent,  getArguments, NULL, ZEND_ACC_PUBLIC)

    PHP_ME(Symfony_Component_EventDispatcher_GenericEvent,  setArgument,  arginfo_GenericEvent_setArgument, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_GenericEvent,  getArgument,  arginfo_GenericEvent_getArgument, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_GenericEvent,  hasArgument,  arginfo_GenericEvent_getArgument, ZEND_ACC_PUBLIC)

    ZEND_NAMED_ME(offsetSet,    ZEND_MN(Symfony_Component_EventDispatcher_GenericEvent_setArgument), arginfo_GenericEvent_setArgument, ZEND_ACC_PUBLIC)
    ZEND_NAMED_ME(offsetGet,    ZEND_MN(Symfony_Component_EventDispatcher_GenericEvent_getArgument), arginfo_GenericEvent_getArgument, ZEND_ACC_PUBLIC)
    ZEND_NAMED_ME(offsetExists, ZEND_MN(Symfony_Component_EventDispatcher_GenericEvent_hasArgument), arginfo_GenericEvent_getArgument, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_GenericEvent,  offsetUnset,  arginfo_GenericEvent_getArgument, ZEND_ACC_PUBLIC)

    PHP_ME(Symfony_Component_EventDispatcher_GenericEvent,  getIterator,  NULL, ZEND_ACC_PUBLIC)

    PHP_FE_END
};
