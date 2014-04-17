/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#include "event.h"

zend_class_entry *event_ce;

static zend_function_entry event_methods[];
static zend_object_handlers event_object_handlers;
static void event_free_storage_handler(event_object * TSRMLS_DC);
static zend_object_value event_create_object(zend_class_entry * TSRMLS_DC);

PHP_MINIT_FUNCTION(event_class)
{
	zend_class_entry ce;
	INIT_CLASS_ENTRY(ce, ZEND_NS_NAME(EVENT_DISPATCHER_NS, "Event"), event_methods);
	event_ce = zend_register_internal_class(&ce TSRMLS_CC);

	event_ce->create_object = event_create_object;
	memcpy(&event_object_handlers, zend_get_std_object_handlers(), sizeof(zend_object_handlers));

	return SUCCESS;
}

static void event_free_storage_handler(event_object *obj TSRMLS_DC)
{
	if (obj->dispatcher) {
		zval_ptr_dtor(&obj->dispatcher);
	}
	zend_object_std_dtor(&obj->zobj TSRMLS_CC);
	if (obj->name.name) {
		efree(obj->name.name);
	}
	efree(obj);
}

static zend_object_value event_create_object(zend_class_entry *ce TSRMLS_DC)
{
	zend_object_value retval;

	event_object *event = ecalloc(1, sizeof(event_object));
	ZEND_OBJ_INIT(&event->zobj, ce);

	retval.handle   = zend_objects_store_put(event, (zend_objects_store_dtor_t)zend_objects_destroy_object, (zend_objects_free_object_storage_t) event_free_storage_handler, NULL TSRMLS_CC);
	retval.handlers = &event_object_handlers;

	return retval;
}

PHP_METHOD(Symfony_Component_EventDispatcher_Event, isPropagationStopped)
{
	FETCH_EVENT_OBJECT

	RETURN_BOOL(event->propagationStopped);
}

PHP_METHOD(Symfony_Component_EventDispatcher_Event, stopPropagation)
{
	FETCH_EVENT_OBJECT

	event->propagationStopped = 1;
}

PHP_METHOD(Symfony_Component_EventDispatcher_Event, getName)
{
	FETCH_EVENT_OBJECT

	if (!event->name.name) {
		return;
	}

	RETURN_STRINGL(event->name.name, event->name.name_len, 1);
}

PHP_METHOD(Symfony_Component_EventDispatcher_Event, setName)
{
	char *value = NULL;
	int value_len = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &value, &value_len) == FAILURE) {
		return;
	}

	FETCH_EVENT_OBJECT

	EVENT_SET_NAME(event, value, value_len);
}

PHP_METHOD(Symfony_Component_EventDispatcher_Event, getDispatcher)
{
	FETCH_EVENT_OBJECT

	if (!event->dispatcher) {
		return;
	}

	RETURN_ZVAL(event->dispatcher, 1, 0);
}

PHP_METHOD(Symfony_Component_EventDispatcher_Event, setDispatcher)
{
	zval *dispatcher = NULL;
	FETCH_EVENT_OBJECT

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "O", &dispatcher, event_dispatcher_interface_ce) == FAILURE) {
		return;
	}

	if (event->dispatcher) {
		zval_ptr_dtor(&event->dispatcher);
	}

	event->dispatcher = dispatcher;
	Z_ADDREF_P(dispatcher);
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_Event_setDispatcher, 0, 0, 0)
    ZEND_ARG_OBJ_INFO(0, dispatcher, Symfony\\Component\\EventDispatcher\\EventDispatcherInterface, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_Event_setName, 0, 0, 1)
    ZEND_ARG_INFO(0, name)
ZEND_END_ARG_INFO()

static zend_function_entry event_methods[] = {
    PHP_ME(Symfony_Component_EventDispatcher_Event,  isPropagationStopped,  NULL, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_Event,  stopPropagation,       NULL, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_Event,  setDispatcher,         arginfo_Event_setDispatcher, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_Event,  getDispatcher,         NULL, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_Event,  getName,               NULL, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_Event,  setName,               arginfo_Event_setName, ZEND_ACC_PUBLIC)
    PHP_FE_END
};
