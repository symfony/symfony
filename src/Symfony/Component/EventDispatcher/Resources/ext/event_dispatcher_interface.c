/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#include "event_dispatcher_main.h"

zend_class_entry *event_dispatcher_interface_ce;
static zend_function_entry event_dispatcher_interface_methods[];

PHP_MINIT_FUNCTION(event_dispatcher_interface)
{
  zend_class_entry ce;
  INIT_CLASS_ENTRY(ce, ZEND_NS_NAME(EVENT_DISPATCHER_NS, "EventDispatcherInterface"), event_dispatcher_interface_methods);
  event_dispatcher_interface_ce = zend_register_internal_interface(&ce TSRMLS_CC);

  return SUCCESS;
}

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcherInterface_dispatch, 0, 0, 1)
    ZEND_ARG_INFO(0, eventName)
    ZEND_ARG_OBJ_INFO(0, event, Symfony\\Component\\EventDispatcher\\Event, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcherInterface_addListener, 0, 0, 2)
    ZEND_ARG_INFO(0, eventName)
    ZEND_ARG_INFO(0, listener)
    ZEND_ARG_INFO(0, priority)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcherInterface_addSubscriber, 0, 0, 1)
    ZEND_ARG_OBJ_INFO(0, subscriber, Symfony\\Component\\EventDispatcher\\EventSubscriberInterface, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcherInterface_removeListener, 0, 0, 2)
    ZEND_ARG_INFO(0, eventName)
    ZEND_ARG_INFO(0, listener)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcherInterface_removeSubscriber, 0, 0, 1)
    ZEND_ARG_OBJ_INFO(0, subscriber, Symfony\\Component\\EventDispatcher\\EventSubscriberInterface, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcherInterface_getListeners, 0, 0, 0)
    ZEND_ARG_INFO(0, eventName)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_EventDispatcherInterface_hasListeners, 0, 0, 0)
    ZEND_ARG_INFO(0, eventName)
ZEND_END_ARG_INFO()

static zend_function_entry event_dispatcher_interface_methods[] = {
    PHP_ABSTRACT_ME( Symfony_Component_EventDispatcher_EventDispatcherInterface, dispatch, arginfo_EventDispatcherInterface_dispatch)
    PHP_ABSTRACT_ME( Symfony_Component_EventDispatcher_EventDispatcherInterface, addListener, arginfo_EventDispatcherInterface_addListener)
    PHP_ABSTRACT_ME( Symfony_Component_EventDispatcher_EventDispatcherInterface, addSubscriber, arginfo_EventDispatcherInterface_addSubscriber)
    PHP_ABSTRACT_ME( Symfony_Component_EventDispatcher_EventDispatcherInterface, removeListener, arginfo_EventDispatcherInterface_removeListener)
    PHP_ABSTRACT_ME( Symfony_Component_EventDispatcher_EventDispatcherInterface, removeSubscriber, arginfo_EventDispatcherInterface_removeSubscriber)
    PHP_ABSTRACT_ME( Symfony_Component_EventDispatcher_EventDispatcherInterface, getListeners, arginfo_EventDispatcherInterface_getListeners)
    PHP_ABSTRACT_ME( Symfony_Component_EventDispatcher_EventDispatcherInterface, hasListeners, arginfo_EventDispatcherInterface_hasListeners)
    PHP_FE_END
};
