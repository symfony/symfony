/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#include "event_dispatcher_main.h"

zend_class_entry *event_subscriber_interface_ce;
static zend_function_entry event_subscriber_interface_methods[];

PHP_MINIT_FUNCTION(event_subscriber_interface)
{
  zend_class_entry ce;
  INIT_CLASS_ENTRY(ce, ZEND_NS_NAME(EVENT_DISPATCHER_NS, "EventSubscriberInterface"), event_subscriber_interface_methods);
  event_subscriber_interface_ce = zend_register_internal_interface(&ce TSRMLS_CC);

  return SUCCESS;
}

static zend_function_entry event_subscriber_interface_methods[] = {
    ZEND_FENTRY(getSubscribedEvents, NULL, NULL, ZEND_ACC_STATIC|ZEND_ACC_PUBLIC|ZEND_ACC_ABSTRACT)
    PHP_FE_END
};
