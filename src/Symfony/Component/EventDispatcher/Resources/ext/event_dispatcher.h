/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#ifndef PHP_SF_EVENT_DISPATCHER_H
#define PHP_SF_EVENT_DISPATCHER_H 1

#include "event_dispatcher_main.h"
#include "event_dispatcher_internal_API.h"

zend_class_entry *event_dispatcher_ce;
zend_object_handlers event_dispatcher_object_handlers;

PHP_MINIT_FUNCTION(event_dispatcher_class);
PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, doDispatch);
PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, removeSubscriber);
PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, addSubscriber);
PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, removeListener);
PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, hasListeners);
PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, dispatch);
PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, getListeners);
PHP_METHOD(Symfony_Component_EventDispatcher_EventDispatcher, addListener);


#define SUBSCRIBERS_ALLOCATE_NEW_LISTENER_FOR_INSERT do { \
	ALLOC_INIT_ZVAL(listener_zval); Z_DELREF_P(listener_zval); /* refcount is left to zero as we don't use the zval but ed_add_listener() will use it and  increment it*/ \
	array_init_size(listener_zval, 2); \
	add_next_index_zval(listener_zval, subscriber); Z_ADDREF_P(subscriber); \
	listener.zval_listener = listener_zval; \
	listener.priority = 0; \
} while (0);

#if IS_AT_LEAST_PHP_54
#define SUBSCRIBERS_CALL__GET_SUBSCRIBED_EVENTS do { \
	zend_function *fn_proxy = NULL; \
	fn_proxy = zend_std_get_static_method(Z_OBJCE_P(subscriber), "getSubscribedEvents", sizeof("getSubscribedEvents") - 1, NULL TSRMLS_CC); \
	if (!zend_call_method_with_0_params(&subscriber, Z_OBJCE_P(subscriber), &fn_proxy, "getSubscribedEvents", &retval)) { \
		return; \
	} \
\
	if (Z_TYPE_P(retval) != IS_ARRAY) { \
		zval_ptr_dtor(&retval); \
	return; \
	} \
} while (0);
#else
#define SUBSCRIBERS_CALL__GET_SUBSCRIBED_EVENTS do { \
	zend_function *fn_proxy = NULL; \
	fn_proxy = zend_std_get_static_method(Z_OBJCE_P(subscriber), "getSubscribedEvents", sizeof("getSubscribedEvents") - 1 TSRMLS_CC); \
	if (!zend_call_method_with_0_params(&subscriber, Z_OBJCE_P(subscriber), &fn_proxy, "getSubscribedEvents", &retval)) { \
		return; \
	} \
\
	if (Z_TYPE_P(retval) != IS_ARRAY) { \
		zval_ptr_dtor(&retval); \
	return; \
	} \
} while (0);

#endif

#define SUBSCRIBERS_CHECK_KEY_IS_STRING(ht, eventName, eventName_len) do { \
		ulong dummy_key; \
		if (zend_hash_get_current_key_ex((ht), &(eventName), &(eventName_len), &dummy_key, 0, &pos) != HASH_KEY_IS_STRING) { \
			ZEND_HASH_ITERATE_SKIP; \
		} \
		eventName_len--; /* eventName is a zend_hash key, it is then +1'ed */ \
} while (0);

#define SUBSCRIBERS_IS_ARRAY_AND_HAS_KEY_0(zval_p, key_value) \
		(Z_TYPE_P((zval_p)) == IS_ARRAY && \
		zend_hash_index_find(Z_ARRVAL_P((zval_p)), 0, (void **)&(key_value)) == SUCCESS)

#define ADD_NEXT_INDEX_ZVAL_ADDREF(dst, src) add_next_index_zval((dst), (src)); Z_ADDREF_P((src));

#define FETCH_ED_OBJECT FETCH_OBJECT(event_dispatcher, ed)

#endif
