/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#ifndef EVENT_DISPATCHER_MAIN_H_
#define EVENT_DISPATCHER_MAIN_H_

#include "config.h"
#include "php.h"
#include "zend.h"
#include "zend_API.h"
#include "Zend/zend_hash.h"
#include "Zend/zend_interfaces.h"
#include "Zend/zend_exceptions.h"
#include "zend_operators.h"
#include "ext/spl/spl_exceptions.h"
#include "ext/spl/spl_array.h"
#include "ext/standard/php_var.h"
#include "ext/standard/php_array.h"

#include "sensiolabs_php_compat.h"
#include "sensiolabs_php_utils.h"

#include "event.h"
#include "generic_event.h"
#include "event_dispatcher_interface.h"
#include "event_subscriber_interface.h"
#include "traceable_event_dispatcher_interface.h"
#include "event_dispatcher.h"

#define EVENTDISPATCHER_VERSION "2.4"
#define EVENT_DISPATCHER_NS "Symfony\\Component\\EventDispatcher"

#endif /* EVENT_DISPATCHER_MAIN_H_ */
