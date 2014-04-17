/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#ifndef PHP_SF_GENERIC_EVENT_H
#define PHP_SF_GENERIC_EVENT_H 1

extern zend_class_entry *generic_event_ce;

PHP_MINIT_FUNCTION(generic_event_class);
PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, __construct);
PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, getSubject);
PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, setArguments);
PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, getArguments);
PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, setArgument);
PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, hasArgument);
PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, getArgument);
PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, offsetUnset);
PHP_METHOD(Symfony_Component_EventDispatcher_GenericEvent, getIterator);

#endif
