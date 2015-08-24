/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


#ifndef PHP_SF_EVENT_SUBSCRIBER_INTERFACE_H
#define PHP_SF_EVENT_SUBSCRIBER_INTERFACE_H 1

extern zend_class_entry *event_subscriber_interface_ce;

PHP_MINIT_FUNCTION(event_subscriber_interface);

#endif
