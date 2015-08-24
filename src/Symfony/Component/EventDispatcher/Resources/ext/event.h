/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#ifndef PHP_SF_EVENT_H
#define PHP_SF_EVENT_H 1

#include "event_dispatcher_main.h"

struct _event_object_name {
	char *name;
	int name_len;
};

struct _event_object {
	zend_object zobj;
	struct _event_object_name name;
	zend_bool propagationStopped;
	zval *dispatcher;
};

typedef struct _event_object event_object;

extern zend_class_entry *event_ce;

PHP_METHOD(Symfony_Component_EventDispatcher_Event, isPropagationStopped);
PHP_METHOD(Symfony_Component_EventDispatcher_Event, stopPropagation);
PHP_METHOD(Symfony_Component_EventDispatcher_Event, getName);
PHP_METHOD(Symfony_Component_EventDispatcher_Event, setName);
PHP_METHOD(Symfony_Component_EventDispatcher_Event, getDispatcher);
PHP_METHOD(Symfony_Component_EventDispatcher_Event, setDispatcher);

#define FETCH_EVENT_OBJECT FETCH_OBJECT(event_object, event)

#define EVENT_SET_NAME(event, _name, _name_len) do { \
											if (event->name.name) { \
												efree(event->name.name); \
											} \
											event->name.name = estrndup(_name, _name_len); \
											event->name.name_len = _name_len; \
} while(0);

PHP_MINIT_FUNCTION(event_class);

#endif
