/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#ifndef EVENT_DISPATCHER_MODULE_H_
#define EVENT_DISPATCHER_MODULE_H_

#include "event_dispatcher_main.h"

extern zend_module_entry symfony_eventdispatcher_module_entry;
zend_module_entry *get_module(void);

PHP_MINIT_FUNCTION(event_dispatcher_module);
PHP_MSHUTDOWN_FUNCTION(event_dispatcher_module);
PHP_MINFO_FUNCTION(event_dispatcher_module);

#define EVENT_DISPATCHER_VERSION "2.4"

#endif /* EVENT_DISPATCHER_MODULE_H_ */
