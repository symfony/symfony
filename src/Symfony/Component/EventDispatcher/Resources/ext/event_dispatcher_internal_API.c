/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#include "event_dispatcher_main.h"
#include "event_dispatcher_internal_API.h"

static int ed_remove_listener_cb(void *, void *);
static void ed_listener_dtor(ed_listener *);
static int ed_sort_listeners(const zend_llist_element **, const zend_llist_element **);

int ed_add_listener(zval* ed_obj, char* eventName, int eventName_len, ed_listener* _listener TSRMLS_DC)
{
	event_dispatcher *ed = zend_object_store_get_object(ed_obj TSRMLS_CC);
	zend_llist **event = NULL;

	if (zend_hash_find(ed->events, eventName, eventName_len + 1, (void **)&event) == FAILURE) {
		event_dispatcher_llist *new_event = NULL;
		new_event = ecalloc(1, sizeof(event_dispatcher_llist));
		new_event->sorted = 0;
		zend_llist_init((zend_llist *)new_event, sizeof(ed_listener), (llist_dtor_func_t)ed_listener_dtor, 0); /* llist duplicates data */
		zend_hash_add(ed->events, eventName, eventName_len + 1, &new_event, sizeof(zend_llist *), NULL);
		event = (zend_llist **)&new_event;
	}

	zend_llist_add_element(*event, _listener);
	Z_ADDREF_P(_listener->zval_listener);

	return SUCCESS;
}

void ed_remove_listener(zend_llist *listeners, zval *listener)
{
	zend_llist_del_element(listeners, (void *)listener, ed_remove_listener_cb);
}

void ed_llist_apply(zend_llist *l, ed_llist_apply_with_args_func_t func TSRMLS_DC, int num_args, ...)
{
	zend_llist_element *element      = NULL;
	zend_llist_element *next_element = NULL;
	va_list args;
	int stop = 0;

	element = l->head;
	while (element) {
		next_element = element->next;
		va_start(args, num_args);
		stop = func(element->data, num_args, args TSRMLS_CC);
		va_end(args);
		if (stop) {
			return;
		}
	element = next_element;
	}
}

void ed_event_dtor(zend_llist **ll_listener)
{
	zend_llist_destroy(*ll_listener);
	efree(*ll_listener);
}

static void ed_listener_dtor(ed_listener *listener)
{
	zval_ptr_dtor(&listener->zval_listener);
}

static int ed_sort_listeners(const zend_llist_element **e1, const zend_llist_element **e2)
{
	return ((ed_listener*)(*e1)->data)->priority <= ((ed_listener*)(*e2)->data)->priority;
}

int ed_turn_all_listeners_to_hashtable_cb(zend_llist **listeners TSRMLS_DC, int num_args, va_list args, zend_hash_key *key)
{
	zval *retval = va_arg(args, zval *);
	zval *newArray = NULL;
	ALLOC_INIT_ZVAL(newArray);
	array_init(newArray);

	zend_hash_add(Z_ARRVAL_P(retval), key->arKey, key->nKeyLength, &newArray, sizeof(zval *), NULL);
	zend_llist_apply_with_argument(*listeners, (llist_apply_with_arg_func_t)ed_turn_listeners_to_hashtable_cb, Z_ARRVAL_P(newArray) TSRMLS_CC);

	return ZEND_HASH_APPLY_KEEP;
}

int ed_sort_all_listeners_cb(zend_llist **listeners TSRMLS_DC)
{
	SORT_LLIST_IF_NEEDED((event_dispatcher_llist *)(*listeners));

	return ZEND_HASH_APPLY_KEEP;
}

void ed_turn_listeners_to_hashtable_cb(ed_listener *a, HashTable *b TSRMLS_DC)
{
	zend_hash_next_index_insert(b, &a->zval_listener, sizeof(zval *), NULL);
	Z_ADDREF_P(a->zval_listener);
}

int ed_count_all_listeners_cb(zend_llist **listeners, long *count)
{
	*count += zend_llist_count(*listeners);

	return ZEND_HASH_APPLY_KEEP;
}

zend_llist* ed_get_listeners_for_event(zval *ed_obj, char *eventName, int eventName_len TSRMLS_DC)
{
	event_dispatcher *ed = zend_object_store_get_object(ed_obj TSRMLS_CC);
	zend_llist **listeners = NULL;

	if (zend_hash_find(ed->events, eventName, eventName_len + 1, (void **)&listeners) == FAILURE) {
		return NULL;
	}

	SORT_LLIST_IF_NEEDED((event_dispatcher_llist *)(*listeners));

	return *listeners;
}

static int ed_remove_listener_cb(void *e1, void *e2)
{
	zval result;

	TSRMLS_FETCH();

	if ( ((ed_listener *)e1)->zval_listener == (zval *)e2 ) {
		return 1;
	}

	is_identical_function(&result, ((ed_listener *)e1)->zval_listener, (zval *)e2 TSRMLS_CC);

	return Z_LVAL(result);
}

int ed_listeners_do_dispatch_cb(zval *listener_zval TSRMLS_DC, int num_args, va_list args, zend_hash_key *key)
{
	ed_listener *listener = NULL;
	va_list list;
	int retval;

	va_copy(list, args);
	listener = (ed_listener *)listener_zval;
	retval = ed_listener_do_dispatch_cb(listener, num_args, list TSRMLS_CC);
	va_end(list);

	return retval;
}

int ed_listener_do_dispatch_cb(ed_listener *listener, int num_args, va_list args TSRMLS_DC)
{
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;
	char *callable_name = NULL, *error = NULL;

	zval *eventName_zval    = NULL;
	zval *event             = NULL;
	zval *dispatcher        = NULL;
	event_object *event_obj = NULL;

	if (EG(exception)) {
		return 1;
	}

	if (zend_fcall_info_init(listener->zval_listener, IS_CALLABLE_STRICT, &fci, &fcc, &callable_name, &error TSRMLS_CC) == FAILURE) {
		if (error) {
			php_error(E_WARNING, error);
			efree(error);
			if (callable_name) {
				efree(callable_name);
			}
		}
		return 0;
	}

	fcc.initialized = 1;

	eventName_zval  = va_arg(args, zval *);
	event           = va_arg(args, zval *);
	dispatcher      = va_arg(args, zval *);

	zend_fcall_info_argn(&fci TSRMLS_CC, num_args, &event, &eventName_zval, &dispatcher);

	zval *return_value = NULL;
	fci.retval_ptr_ptr = &return_value;

	zend_call_function(&fci, &fcc TSRMLS_CC);

	efree(fci.params);
	if (callable_name) {
		efree(callable_name);
	}

	if (!EG(exception)) {
		zval_ptr_dtor(&return_value);
	}

	event_obj = zend_object_store_get_object(event TSRMLS_CC);

	if (event_obj->propagationStopped) {
		return 1;
	}

	return 0;
}
