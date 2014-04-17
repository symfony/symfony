/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#include "event_dispatcher_main.h"

static zend_function_entry event_dispatcher_methods[];
static void ed_free_object_storage(event_dispatcher * TSRMLS_DC);
static zend_object_value ed_object_create_handler(zend_class_entry * TSRMLS_DC);

static void ed_free_object_storage(event_dispatcher *ed TSRMLS_DC)
{
	zend_object_std_dtor(&ed->zobj TSRMLS_CC);
	zend_hash_destroy(ed->events);
	efree(ed->events);
	efree(ed);
}

static zend_object_value ed_object_create_handler(zend_class_entry *ce TSRMLS_DC)
{
	zend_object_value retval;

	event_dispatcher *ed = (event_dispatcher *)ecalloc(1, sizeof(event_dispatcher));
	ZEND_OBJ_INIT(&ed->zobj, ce);
	ALLOC_HASHTABLE(ed->events);
	zend_hash_init(ed->events, 16, NULL, (dtor_func_t)ed_event_dtor, 0);

	if (ce != event_dispatcher_ce) {
		zend_function *function = NULL;
		if (zend_hash_find(&ce->function_table, ZEND_STRS("dodispatch"), (void *)&function) != FAILURE) {
			if (function->common.scope != event_dispatcher_ce) {
				ed->is_inherited = 1;
			}
		}
	}

	retval.handle = zend_objects_store_put(
		ed,
		(zend_objects_store_dtor_t) zend_objects_destroy_object,
		(zend_objects_free_object_storage_t) ed_free_object_storage,
		NULL TSRMLS_CC);
	retval.handlers = &event_dispatcher_object_handlers;

	return retval;
}

PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, addListener)
{
	zval *zval_listener = NULL;
	long priority = 0;
	char *eventName = NULL;
	int eventName_len;
	ed_listener listener; /* Stack allocated */

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sz|l", &eventName, &eventName_len, &zval_listener, &priority) == FAILURE) {
		return;
	}

	listener.priority      = priority;
	listener.zval_listener = zval_listener;

	ed_add_listener(getThis(), eventName, eventName_len, &listener TSRMLS_CC);
}

PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, getListeners)
{
	char *eventName = NULL;
	int eventName_len;
	zend_llist *listeners = NULL;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|s!", &eventName, &eventName_len) == FAILURE) {
		return;
	}

	array_init(return_value);

	if (eventName) {
		listeners = ed_get_listeners_for_event(getThis(), eventName, eventName_len TSRMLS_CC);
		if (!listeners) {
			return;
		}
		zend_llist_apply_with_argument(listeners, (llist_apply_with_arg_func_t)ed_turn_listeners_to_hashtable_cb, Z_ARRVAL_P(return_value) TSRMLS_CC);
	} else {
		FETCH_ED_OBJECT;
		zend_hash_apply(ed->events, (apply_func_t)ed_sort_all_listeners_cb TSRMLS_CC);
		zend_hash_apply_with_arguments(ed->events TSRMLS_CC, (apply_func_args_t)ed_turn_all_listeners_to_hashtable_cb, 1, return_value);
	}
}

PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, dispatch)
{
	zval *event_zval = NULL;
	char *eventName = NULL;
	int eventName_len;
	zval *eventName_zval = NULL;
	zend_llist *listeners = NULL;
	char free_event = 0;
	event_object *event = NULL;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s|o!", &eventName, &eventName_len, &event_zval) == FAILURE) {
		return;
	}

	if (!event_zval) {
		ALLOC_INIT_ZVAL(event_zval);
		object_init_ex(event_zval, event_ce);
		free_event = 1;
	}

	RETVAL_ZVAL(event_zval, 1, 0);

	FETCH_ED_OBJECT;
	listeners = ed_get_listeners_for_event(getThis(), eventName, eventName_len TSRMLS_CC);

	if (!listeners) {
		return;
	}

	event = zend_object_store_get_object(event_zval TSRMLS_CC);
	event->dispatcher = getThis(); Z_ADDREF_P(getThis());
	EVENT_SET_NAME(event, (eventName), (eventName_len));
	ALLOC_INIT_ZVAL(eventName_zval);
	ZVAL_STRINGL(eventName_zval, eventName, eventName_len, 1);

	if (!ed->is_inherited) {
		ed_llist_apply(listeners, (ed_llist_apply_with_args_func_t)ed_listener_do_dispatch_cb TSRMLS_CC, 3, eventName_zval, event_zval, getThis());
	} else {
		zend_fcall_info fci       = {0};
		zend_fcall_info_cache fcc = {0};
		zval function_name;
		zval *retval              = NULL;
		zval *listeners_zval      = NULL;

		INIT_ZVAL(function_name);
		ALLOC_INIT_ZVAL(listeners_zval);
		array_init(listeners_zval);
		zend_llist_apply_with_argument(listeners, (llist_apply_with_arg_func_t)ed_turn_listeners_to_hashtable_cb, Z_ARRVAL_P(listeners_zval) TSRMLS_CC);

		ZVAL_STRINGL(&function_name, "dodispatch", sizeof("dodispatch") -1, 1);
		zend_fcall_info_argn(&fci TSRMLS_CC, 3, &listeners_zval, &eventName_zval, &event_zval);
		fci.size           = sizeof(fci);
		fci.object_ptr     = getThis();
		fci.function_name  = &function_name;
		fci.no_separation  = 1;
		fci.retval_ptr_ptr = &retval;

		fcc.calling_scope = Z_OBJCE_P(getThis());
		fcc.called_scope  = Z_OBJCE_P(getThis());
		fcc.object_ptr    = getThis();
		fcc.initialized   = 1;

		zend_hash_find(&Z_OBJCE_P(getThis())->function_table, "dodispatch", sizeof("dodispatch"), (void **)&fcc.function_handler);

		zend_call_function(&fci, &fcc TSRMLS_CC);

		zval_ptr_dtor(&listeners_zval);
		zval_dtor(&function_name);
		efree(fci.params);
		if (!EG(exception)) {
			RETVAL_ZVAL(retval, 1, 1);
		}
	}

	zval_ptr_dtor(&eventName_zval);
	if (free_event) {
		zval_ptr_dtor(&event_zval);
	}
}

PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, hasListeners)
{
	char *eventName = NULL;
	int eventName_len;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "|s!", &eventName, &eventName_len) == FAILURE) {
		return;
	}

	if (eventName) {
		zend_llist *listeners = NULL;
		listeners = ed_get_listeners_for_event(getThis(), eventName, eventName_len TSRMLS_CC);
		if (!listeners) {
			RETURN_BOOL(0);
		}
		RETURN_BOOL(zend_llist_count(listeners));
	} else {
		FETCH_ED_OBJECT
		long num = 0;
		zend_hash_apply_with_argument(ed->events, (apply_func_arg_t)ed_count_all_listeners_cb, &num TSRMLS_CC);
		RETURN_BOOL(num);
	}
}

PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, removeListener)
{
	char *eventName = NULL;
	int eventName_len;
	zend_llist **listeners = NULL;
	zval *listener = NULL;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sz", &eventName, &eventName_len, &listener) == FAILURE) {
		return;
	}

	FETCH_ED_OBJECT

	if (zend_hash_find(ed->events, eventName, eventName_len + 1, (void **)&listeners) == FAILURE) {
		return;
	}

	ed_remove_listener(*listeners, listener);
}

PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, addSubscriber)
{
	zval *subscriber = NULL, *retval = NULL, **params = NULL, **param0 = NULL, *listener_zval = NULL;
	char *eventName = NULL;
	uint eventName_len;
	ed_listener listener = {0};

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "o", &subscriber) == FAILURE) {
		return;
	}

	SUBSCRIBERS_CALL__GET_SUBSCRIBED_EVENTS

	ZEND_HASH_ITERATE_START(Z_ARRVAL_P(retval), params)
		SUBSCRIBERS_CHECK_KEY_IS_STRING(Z_ARRVAL_P(retval), eventName, eventName_len)
		if (Z_TYPE_PP(params) == IS_STRING) {
			SUBSCRIBERS_ALLOCATE_NEW_LISTENER_FOR_INSERT;
			ADD_NEXT_INDEX_ZVAL_ADDREF(listener_zval, *params);
			ed_add_listener(getThis(), eventName, eventName_len, &listener TSRMLS_CC);
		} else if(SUBSCRIBERS_IS_ARRAY_AND_HAS_KEY_0(*params, param0) && Z_TYPE_PP(param0) == IS_STRING) {
			SUBSCRIBERS_ALLOCATE_NEW_LISTENER_FOR_INSERT;
			ADD_NEXT_INDEX_ZVAL_ADDREF(listener_zval, *param0);
			if (zend_hash_index_find(Z_ARRVAL_PP(params), 1, (void **)&param0) == SUCCESS && Z_TYPE_PP(param0) == IS_LONG) {
				listener.priority = Z_LVAL_PP(param0);
			}
			ed_add_listener(getThis(), eventName, eventName_len, &listener TSRMLS_CC);
		} else if(Z_TYPE_PP(params) == IS_ARRAY) {
			zval **value = NULL, **value0 = NULL, **value1 = NULL;
			ZEND_HASH_ITERATE_START(Z_ARRVAL_PP(params), value)
				if (Z_TYPE_PP(value) != IS_ARRAY) {
					continue;
				}
				SUBSCRIBERS_ALLOCATE_NEW_LISTENER_FOR_INSERT;
				zend_hash_index_find(Z_ARRVAL_PP(value), 0, (void **)&value0);
				ADD_NEXT_INDEX_ZVAL_ADDREF(listener_zval, *value0);
				if (zend_hash_index_find(Z_ARRVAL_PP(value), 1, (void **)&value1) == SUCCESS && Z_TYPE_PP(value1) == IS_LONG) {
					listener.priority = Z_LVAL_PP(value1);
				}
				ed_add_listener(getThis(), eventName, eventName_len, &listener TSRMLS_CC);
			ZEND_HASH_ITERATE_END
		} else {
			ZEND_HASH_ITERATE_SKIP;
		}
	ZEND_HASH_ITERATE_END

	zval_ptr_dtor(&retval);
}

PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, removeSubscriber)
{
	zval *retval = NULL, *subscriber = NULL, **params = NULL;
	char *eventName = NULL;
	uint eventName_len = 0;
	zend_llist **listeners = NULL;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "o", &subscriber) == FAILURE) {
		return;
	}

	SUBSCRIBERS_CALL__GET_SUBSCRIBED_EVENTS

	FETCH_ED_OBJECT

	ZEND_HASH_ITERATE_START(Z_ARRVAL_P(retval), params)
		zval *listener = NULL, **param0 = NULL;
		SUBSCRIBERS_CHECK_KEY_IS_STRING(Z_ARRVAL_P(retval), eventName, eventName_len)
		if (zend_hash_find(ed->events, eventName, eventName_len + 1, (void **)&listeners) == FAILURE) {
			continue;
		}
		if ( Z_TYPE_PP(params) == IS_ARRAY &&
		     zend_hash_index_find(Z_ARRVAL_PP(params), 0, (void **)&param0) == SUCCESS &&
		     Z_TYPE_PP(param0) == IS_ARRAY) {
				ZEND_HASH_ITERATE_START(Z_ARRVAL_PP(params), param0)
					zval **listener_name = NULL;
					if (!SUBSCRIBERS_IS_ARRAY_AND_HAS_KEY_0(*param0, listener_name)) {
						ZEND_HASH_ITERATE_SKIP;
					}
					ALLOC_INIT_ZVAL(listener);
					array_init_size(listener, 2);
					ADD_NEXT_INDEX_ZVAL_ADDREF(listener, subscriber);
					ADD_NEXT_INDEX_ZVAL_ADDREF(listener, *listener_name)
					ed_remove_listener(*listeners, listener);
					zval_ptr_dtor(&listener);
				ZEND_HASH_ITERATE_END
		} else {
			zval *listener = NULL;
			ALLOC_INIT_ZVAL(listener);
			array_init_size(listener, 2);
			ADD_NEXT_INDEX_ZVAL_ADDREF(listener, subscriber);
			if (Z_TYPE_PP(params) == IS_STRING) {
				ADD_NEXT_INDEX_ZVAL_ADDREF(listener, *params);
			} else if(SUBSCRIBERS_IS_ARRAY_AND_HAS_KEY_0(*params, param0)) {
				ADD_NEXT_INDEX_ZVAL_ADDREF(listener, *param0);
			} else {
				ZEND_HASH_ITERATE_SKIP;
			}
			ed_remove_listener(*listeners, listener);
			zval_ptr_dtor(&listener);
		}
	ZEND_HASH_ITERATE_END

	zval_ptr_dtor(&retval);
}

PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, doDispatch)
{
	HashTable *listeners = NULL;
	zval *eventName_zval = NULL;
	zval *event = NULL;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "hzz", &listeners, &eventName_zval, &event) == FAILURE) {
		return;
	}

	zend_hash_apply_with_arguments(listeners TSRMLS_CC, (apply_func_args_t)ed_listeners_do_dispatch_cb, 3, eventName_zval, event, getThis());
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcher_dispatch, 0, 0, 1)
    ZEND_ARG_INFO(0, eventName)
    ZEND_ARG_OBJ_INFO(0, event, Symfony\\Component\\EventDispatcher\\Event, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcher_getListeners, 0, 0, 0)
    ZEND_ARG_INFO(0, eventName)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcher_addListener, 0, 0, 2)
    ZEND_ARG_INFO(0, eventName)
    ZEND_ARG_INFO(0, ed_listener)
    ZEND_ARG_INFO(0, priority)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcher_removeListener, 0, 0, 2)
    ZEND_ARG_INFO(0, eventName)
    ZEND_ARG_INFO(0, listener)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcher_addSubscriber, 0, 0, 1)
    ZEND_ARG_OBJ_INFO(0, subscriber, Symfony\\Component\\EventDispatcher\\EventSubscriberInterface, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcher_doDispatch, 0, 0, 3)
    ZEND_ARG_INFO(0, listeners)
    ZEND_ARG_INFO(0, eventName)
    ZEND_ARG_OBJ_INFO(0, event, Symfony\\Component\\EventDispatcher\\Event, 0)
ZEND_END_ARG_INFO()

static zend_function_entry event_dispatcher_methods[] = {
    PHP_ME(Symfony_Component_EventDispatcher_EventDispatcher,  dispatch,          arginfo_EventDispatcher_dispatch, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_EventDispatcher,  getListeners,      arginfo_EventDispatcher_getListeners, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_EventDispatcher,  hasListeners,      arginfo_EventDispatcher_getListeners, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_EventDispatcher,  addListener,       arginfo_EventDispatcher_addListener, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_EventDispatcher,  removeListener,    arginfo_EventDispatcher_removeListener, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_EventDispatcher,  addSubscriber,     arginfo_EventDispatcher_addSubscriber, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_EventDispatcher,  removeSubscriber,  arginfo_EventDispatcher_addSubscriber, ZEND_ACC_PUBLIC)
    PHP_ME(Symfony_Component_EventDispatcher_EventDispatcher,  doDispatch,        arginfo_EventDispatcher_doDispatch, ZEND_ACC_PROTECTED)
    PHP_FE_END
};

PHP_MINIT_FUNCTION(event_dispatcher_class)
{
	zend_class_entry ce;
	INIT_CLASS_ENTRY(ce, ZEND_NS_NAME(EVENT_DISPATCHER_NS, "EventDispatcher"), event_dispatcher_methods);
	event_dispatcher_ce = zend_register_internal_class(&ce TSRMLS_CC);
	zend_class_implements(event_dispatcher_ce TSRMLS_CC, 1, event_dispatcher_interface_ce);

	event_dispatcher_ce->create_object = ed_object_create_handler;
	memcpy(&event_dispatcher_object_handlers, zend_get_std_object_handlers(), sizeof(zend_object_handlers));

	return SUCCESS;
}
