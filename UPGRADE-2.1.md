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

* [HttpFoundation] - moved management of the locale from the Session class to the Request class

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

* [HttpFoundation] Flash Messages.  Moved to own bucket and returns and array based on type.

  Before (PHP):

  <?php if ($view['session']->hasFlash('notice')): ?>
      <div class="flash-notice">
          <?php echo $view['session']->getFlash('notice') ?>
      </div>
  <?php endif; ?>

  After (PHP):

      <?php foreach ($view['session']->flashGet(Symfony\Component\HttpFoundation\FlashBag::NOTICE) as $notice): ?>
          <div class="flash-notice">
          <?php echo $notice; ?>
      </div>
  <?php endforeach; ?>

.. note::

    You can of course declare `<?php use Symfony\Component\HttpFoundation\FlashBag; ?>` at the beginning
    of the PHP template so you can use the shortcut `FlashBag::NOTICE`.

  Before (Twig):

  {% if app.session.hasFlash('notice') %}
      <div class="flash-notice">
          {{ app.session.flash('notice') }}
      </div>
  {% endif %}

  After (Twig):

  {% for flashMessage in app.session.flashGet(constant(Symfony\Component\HttpFoundation\FlashBag::NOTICE)) %}
      <div class="flash-notice">
          {{ flashMessage }}
      </div>
  {% endforeach %}

* [HttpFoundation] Session object now requires two additional constructor arguments but will default to
                   sensible defaults for convenience.  The methods, `setFlashes()`, `setFlash()`, `hasFlash()`,
                   `removeFlash()`, and `clearFlashes()` have all been removed from the `Session` object.
                   You may use `addFlash()` to add flashes.  `getFlash()` now returns an array for display.
                   `getFlashes()` returns the FlashBagInterface if you need to deeply manipulate the flash message
                   container.

* [HttpFoundation] Session storage drivers should inherit from
                   `Symfony\Component\HttpFoundation\SessionStorage\AbstractSessionStorage`
                   and no longer should implement `read()`, `write()`, `remove()` which were removed from the
                   `SessionStorageInterface`.

* [HttpFoundation] Any session storage drive that wants to use custom save handlers should
                   implement `Symfony\Component\HttpFoundation\SessionStorage\SessionSaveHandlerInterface`

* [FrameworkBundle] The service session.storage.native is now called `session.storage.native_file`

* [FrameworkBundle] The service `session.storage.filesystem` is deprecated and should be replaced
                    `session.storage.native_file`

