/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#ifndef PHP_SF_EVENT_DISPATCHER_INTERNAL_H
#define PHP_SF_EVENT_DISPATCHER_INTERNAL_H 1

#include "event_dispatcher_main.h"

typedef struct _listener {
	zval *zval_listener;
	long priority;
} ed_listener;

typedef struct _event_dispatcher {
	zend_object zobj;
	HashTable *events;
	zend_bool is_inherited;
} event_dispatcher;

typedef struct _event_dispatcher_llist {
	zend_llist zllist;
	zend_bool sorted;
} event_dispatcher_llist;

#define SORT_LLIST_IF_NEEDED(llist) do { \
		if ( (llist)->sorted == 0) { \
			zend_llist_sort((zend_llist *)(llist), (llist_compare_func_t)ed_sort_listeners TSRMLS_CC); \
			(llist)->sorted = 1; \
		} \
} while(0);

typedef int (*ed_llist_apply_with_args_func_t)(void *, int, va_list TSRMLS_DC);

void ed_event_dtor(zend_llist **);

void ed_turn_listeners_to_hashtable_cb(ed_listener *, HashTable * TSRMLS_DC);
int ed_turn_all_listeners_to_hashtable_cb(zend_llist ** TSRMLS_DC, int, va_list, zend_hash_key *);
int ed_listener_do_dispatch_cb(ed_listener *, int, va_list TSRMLS_DC);
int ed_listeners_do_dispatch_cb(zval * TSRMLS_DC, int, va_list, zend_hash_key *);

zend_llist* ed_get_listeners_for_event(zval *, char *, int TSRMLS_DC);

int ed_add_listener(zval*, char*, int, ed_listener* TSRMLS_DC);
void ed_remove_listener(zend_llist *, zval *);
int ed_sort_all_listeners_cb(zend_llist ** TSRMLS_DC);
int ed_count_all_listeners_cb(zend_llist **, long *);
void ed_llist_apply(zend_llist *l, ed_llist_apply_with_args_func_t func TSRMLS_DC, int num_args, ...);

#endif
