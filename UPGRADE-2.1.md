UPGRADE FROM 2.0 to 2.1
=======================

* assets_base_urls and base_urls merging strategy has changed

  Unlike most configuration blocks, successive values for
  ``assets_base_urls`` will overwrite each other instead of being merged.
  This behavior was chosen because developers will typically define base
  URL's for each environment. Given that most projects tend to inherit
  configurations (e.g. ``config_test.yml`` imports ``config_dev.yml``)
  and/or share a common base configuration (i.e. ``config.yml``), merging
  could yield a set of base URL's for multiple environments.

* moved management of the locale from the Session class to the Request class

  Configuring the default locale:

  Before:

      framework:
        session:
            default_locale: fr

  After:

      framework:
        default_locale: fr

  Retrieving the locale from a Twig template:

  Before: {{ app.request.session.locale }} or {{ app.session.locale }}
  After: {{ app.request.locale }}

  Retrieving the locale from a PHP template:

  Before: $view['session']->getLocale()
  After: $view['request']->getLocale()

  Retrieving the locale from PHP code:

  Before: $session->getLocale()
  After: $request->getLocale()
