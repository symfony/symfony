/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

#include "event_dispatcher_module.h"
#include "SAPI.h"
#include "ext/standard/info.h"

static const char sensiolabs_logo[] = "<img src=\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAHYAAAAUCAMAAABvRTlyAAAAz1BMVEUAAAAAAAAAAAAsThWB5j4AAACD6T8AAACC6D+C6D6C6D+C6D4AAAAAAACC6D4AAAAAAACC6D8AAAAAAAAAAAAAAAAAAAAAAACC6D4AAAAAAAAAAACC6D4AAAAAAAAAAAAAAAAAAAAAAACC6D8AAACC6D4AAAAAAAAAAAAAAAAAAACC6D8AAACC6D6C6D+B6D+C6D+C6D+C6D8AAACC6D6C6D4AAACC6D/K/2KC6D+B6D6C6D6C6D+C6D8sTxUyWRhEeiEAAACC6D+C5z6B6D7drnEVAAAAQXRSTlMAE3oCNSUuDHFHzxaF9UFsu+irX+zlKzYimaJXktyOSFD6BolxqT7QGMMdarMIpuO28r9EolXKgR16OphfXYd4V14GtB4AAAMpSURBVEjHvVSJctowEF1jjME2RziMwUCoMfd9heZqG4n//6buLpJjkmYm03byZmxJa2nf6u2uQcG2bfhqRN4LoTKBzyGDm68M7mAwcOEdjo4zhA/Rf9Go/CVtTgiRhXfIC3EDH8F/eUX1/9KexRo+QgOdtHDsEe/sM7QT32/+K61Z1LFXcXJxN4pTbu1aTQUzuy2PIA0rDo0/0Aa5XFaJvKaVTrubywXvaa1Wq4Vu/Snr3Y7Aojh4VccwykW2N2oQ8wmjyut6+Q1t5ywIG5Npj1sh5E0B7YOzFDjfuRfaOh3O+MbbVNfTWS9COZk3Obd2su5d0a6IU9KLREbw8gEehWSr1r2sPWciXLG38r5NdW0xu9eioU87omjC9yNaMi5GNf6WppVSOqXCFkmCvMB3p9SROLoYQn5pDgQOujA1xjYvqH+plUdkwnmII8VxR/PKYkrfLLomhVlE3b/LhNbNr7hp0H2JaOc4v8dFB58HSsFTSafaqtY1sT3GO8wsy5rhokYPlRJdjPMajyYqTt1EHF/2uqSWQWmAjCUSmQ1MS3g8Btf1XOsy7YIC0CB1b5Xw1Vhba0zbxiCAQLH9TNPmHJXQUtJAN0KcDsoqLxsNvJrJExa7mKIdp2lRE2WexiS4pqWk/0jROlw6K6bV9YOBDGAuqMJ0bnuUKGB0L27bxgRhGEbzihbhxxXaQC88Vkwq8ldCi86RApWUb0Q+4VDosBCc+1s81lUdnBavH4Zp2mm3O44USwOfvSo9oBiwpFg71lMS1VKJLKljS3j9p+fOTvXXlsSNuEv6YPaZda9uRope0VJfKdo7fPiYfSmvFjXQbkhY0d9hCbBWIktRgEDieDhf1N3wbbkmNNgRy8hyl620yGQat/grV3HMpc2HDKTVmOPFz6ylPCKt/nXcAyV260jaAowwIW0YuBzrOgb/KrddZS9OmJaLgpWK4JX2DDuklcLZSDGcn8Vmx9YDNvT6UsjyBApRyFQVX7Vxm9TGxE16nmfRd8/zQoDmggQOTRh5Hv8pMt9Q/L2JmSwkMCE7dA4BuDjHJwfu0Om4QAhOjrN5XkIatglfiN/bUPdCQFjTYgAAAABJRU5ErkJggg==\">";

PHP_MINIT_FUNCTION(event_class);
PHP_MINIT_FUNCTION(generic_event_class);
PHP_MINIT_FUNCTION(event_subscriber_interface);
PHP_MINIT_FUNCTION(event_dispatcher_interface);
PHP_MINIT_FUNCTION(traceable_event_dispatcher_interface);
PHP_MINIT_FUNCTION(event_dispatcher_class);

PHP_MINIT_FUNCTION(event_dispatcher_module)
{
	PHP_MINIT(event_class)(INIT_FUNC_ARGS_PASSTHRU);
	PHP_MINIT(generic_event_class)(INIT_FUNC_ARGS_PASSTHRU);
	PHP_MINIT(event_subscriber_interface)(INIT_FUNC_ARGS_PASSTHRU);
	PHP_MINIT(event_dispatcher_interface)(INIT_FUNC_ARGS_PASSTHRU);
	PHP_MINIT(traceable_event_dispatcher_interface)(INIT_FUNC_ARGS_PASSTHRU);
	PHP_MINIT(event_dispatcher_class)(INIT_FUNC_ARGS_PASSTHRU);

	return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(event_dispatcher_module)
{
	return SUCCESS;
}

PHP_MINFO_FUNCTION(event_dispatcher_module)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "SensioLabs EventDispatcher C support", "enabled");
	php_info_print_table_row(2, "EventDispatcher supported version", EVENT_DISPATCHER_VERSION);
	php_info_print_table_end();

	php_info_print_box_start(0);
	php_write((void *)ZEND_STRL("SensioLabs EventDispatcher C support developed by Julien Pauli and Tugdual Saunier") TSRMLS_CC);
	if (!sapi_module.phpinfo_as_text) {
		php_write((void *)ZEND_STRL(sensiolabs_logo) TSRMLS_CC);
	}
	php_info_print_box_end();
}

zend_module_entry symfony_eventdispatcher_module_entry = {
    STANDARD_MODULE_HEADER,
    "symfony_eventdispatcher",
    NULL, /* functions */
    PHP_MINIT(event_dispatcher_module),
    PHP_MSHUTDOWN(event_dispatcher_module),
    NULL,                  /* RINIT */
    NULL,                  /* RSHUTDOWN */
    PHP_MINFO(event_dispatcher_module),
    EVENTDISPATCHER_VERSION,
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_SYMFONY_EVENTDISPATCHER
  ZEND_GET_MODULE(symfony_eventdispatcher)
#endif
