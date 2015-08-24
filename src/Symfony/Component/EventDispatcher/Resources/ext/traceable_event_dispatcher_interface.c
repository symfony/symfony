/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#include "event_dispatcher_main.h"

zend_class_entry *traceable_event_dispatcher_interface_ce;

static zend_function_entry traceable_event_dispatcher_interface_methods[] = {
	PHP_ABSTRACT_ME( Symfony_Component_EventDispatcher_Debug_TraceableEventDispatcherInterface, getCalledListeners, NULL)
	PHP_ABSTRACT_ME( Symfony_Component_EventDispatcher_Debug_TraceableEventDispatcherInterface, getNotCalledListeners, NULL)
	PHP_FE_END
};

PHP_MINIT_FUNCTION(traceable_event_dispatcher_interface)
{
	zend_class_entry ce;
	INIT_CLASS_ENTRY(ce, ZEND_NS_NAME(EVENT_DISPATCHER_NS, "Debug\\TraceableEventDispatcherInterface"), traceable_event_dispatcher_interface_methods);
	traceable_event_dispatcher_interface_ce = zend_register_internal_interface(&ce TSRMLS_CC);

	return SUCCESS;
}
