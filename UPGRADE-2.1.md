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

  <?php if ($view['session']->getFlashBag()->has(FlashBag::NOTICE)): ?>
      <?php foreach ($view['session']->getFlashBag()->get(FlashBag::NOTICE, true) as $notice): ?>
          <div class="flash-notice">
          <?php echo $notice; ?>
          </div>
      <?php endforeach; ?>
  <?php endif; ?>

  Before (Twig):

  {% if app.session.hasFlash('notice') %}
      <div class="flash-notice">
          {{ app.session.flash('notice') }}
      </div>
  {% endif %}

  After (Twig): (needs review)

  {% if app.session.getFlashBag.has(FlashBag::NOTICE) %}
      {% for flashMessage in app.session.getFlashBag().get(FlashBag::NOTICE, true) %}
          <div class="flash-notice">
              {{ flashMessage }}
          </div>
      {% endforeach %}
  {% endif %}

* [HttpFoundation] Session storage drivers should inherit from SessionStorage\AbstractSessionStorage.

* [HttpFoundation] SessionStorage\PDOSessionStorage - FlashBagInterface required in constructor.

* [HttpFoundation] SessionStorage\ArraySessionStorage - FlashBagInterface required in constructor.

* [HttpFoundation] Any session storage drive that wants to use non-native PHP save handlers should
                   implement SessionStorage\SessionSaveHandlerInterface

* [FrameworkBundle] The service session.storage.native is now called session.storage.nativefile

* [FrameworkBundle] The service session.storage.filesystem is deprecated and should be replaced 
                    session.storage.nativefile
                      
