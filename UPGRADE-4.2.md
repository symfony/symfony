UPGRADE FROM 4.1 to 4.2
=======================

Cache
-----

 * Deprecated `CacheItem::getPreviousTags()`, use `CacheItem::getMetadata()` instead.

Form
----

 * Deprecated calling `FormRenderer::searchAndRenderBlock` for fields which were already rendered. 
   Instead of expecting such calls to return empty strings, check if the field has already been rendered.
 
   Before:
   ```twig
   {% for field in fieldsWithPotentialDuplicates %}
      {{ form_widget(field) }}
   {% endfor %}
   ```
   
   After:
   ```twig
   {% for field in fieldsWithPotentialDuplicates if not field.rendered %}
      {{ form_widget(field) }}
   {% endfor %}
   ```

Config
------

 * Deprecated constructing a `TreeBuilder` without passing root node information.

Security
--------

 * Using the `has_role()` function in security expressions is deprecated, use the `is_granted()` function instead.
 * Not returning an array of 3 elements from `FirewallMapInterface::getListeners()` is deprecated, the 3rd element
   must be an instance of `LogoutListener` or `null`.
 * Passing custom class names to the
   `Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver` to define
   custom anonymous and remember me token classes is deprecated. To
   use custom tokens, extend the existing `Symfony\Component\Security\Core\Authentication\Token\AnonymousToken`
   or `Symfony\Component\Security\Core\Authentication\Token\RememberMeToken`.

SecurityBundle
--------------

 * Passing a `FirewallConfig` instance as 3rd argument to the `FirewallContext` constructor is deprecated,
   pass a `LogoutListener` instance instead.
 * Using the `security.authentication.trust_resolver.anonymous_class` and
   `security.authentication.trust_resolver.rememberme_class` parameters to define
   the token classes is deprecated. To use
   custom tokens extend the existing AnonymousToken and RememberMeToken.

DoctrineBridge
--------------

 * The `lazy` attribute on `doctrine.event_listener` tags was removed. 
   Listeners are now lazy by default. So any `lazy` attributes can safely be removed from those tags.
