UPGRADE FROM 4.1 to 4.2
=======================

Cache
-----

 * Deprecated `CacheItem::getPreviousTags()`, use `CacheItem::getMetadata()` instead.

Config
------

 * Deprecated constructing a `TreeBuilder` without passing root node information.
 * Deprecated `FileLoaderLoadException`, use `LoaderLoadException` instead.

Console
-------

 * Deprecated passing a command as a string to `ProcessHelper::run()`,
   pass the command as an array of arguments instead.

   Before:
   ```php
   $processHelper->run($output, 'ls -l');
   ```

   After:
   ```php
   $processHelper->run($output, array('ls', '-l'));

   // alternatively, when a shell wrapper is required
   $processHelper->run($output, Process::fromShellCommandline('ls -l'));
   ```

DoctrineBridge
--------------

 * The `lazy` attribute on `doctrine.event_listener` tags was removed.
   Listeners are now lazy by default. So any `lazy` attributes can safely be removed from those tags.

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

Process
-------

 * Deprecated the `Process::setCommandline()` and the `PhpProcess::setPhpBinary()` methods.
 * Deprecated passing commands as strings when creating a `Process` instance.

   Before:
   ```php
   $process = new Process('ls -l');
   ```

   After:
   ```php
   $process = new Process(array('ls', '-l'));

   // alternatively, when a shell wrapper is required
   $process = Process::fromShellCommandline('ls -l');
   ```

FrameworkBundle
---------------

 * The `framework.router.utf8` configuration option has been added. If your app's charset
   is UTF-8 (see kernel's `getCharset()` method), it is recommended to set it to `true`:
   this will generate 404s for non-UTF-8 URLs, which are incompatible with you app anyway,
   and will allow dumping optimized routers and using Unicode classes in requirements.
 * Added support for the SameSite attribute for session cookies. It is highly recommended to set this setting (`framework.session.cookie_samesite`) to `lax` for increased security against CSRF attacks.

Messenger
---------

 * `EnvelopeItemInterface` doesn't extend `Serializable` anymore
 * The `handle` method of the `Symfony\Component\Messenger\Middleware\ValidationMiddleware` and `Symfony\Component\Messenger\Asynchronous\Middleware\SendMessageMiddleware` middlewares now requires an `Envelope` object to be given (because they implement the `EnvelopeAwareInterface`). When using these middleware with the provided `MessageBus`, you will not have to do anything. If you use the middlewares any other way, you can use `Envelope::wrap($message)` to create an envelope for your message.
 * `MessageSubscriberInterface::getHandledMessages()` return value has changed. The value of an array item
   needs to be an associative array or the method name. 
   
   Before:
   ```php
   return [
      [FirstMessage::class, 0],
      [SecondMessage::class, -10],
   ];
   ```
   
   After:
   ```php
   yield FirstMessage::class => ['priority' => 0];
   yield SecondMessage::class => ['priority => -10];
   ```
   
   Before:
   ```php
   return [
       SecondMessage::class => ['secondMessageMethod', 20],
   ];
   ```
   
   After:
   ```php
   yield SecondMessage::class => [
       'method' => 'secondMessageMethod',
       'priority' => 20,
   ];
   ```

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
 * Accessing the user object that is not an instance of `UserInterface` from `Security::getUser()` is deprecated.

SecurityBundle
--------------

 * Passing a `FirewallConfig` instance as 3rd argument to the `FirewallContext` constructor is deprecated,
   pass a `LogoutListener` instance instead.
 * Using the `security.authentication.trust_resolver.anonymous_class` and
   `security.authentication.trust_resolver.rememberme_class` parameters to define
   the token classes is deprecated. To use
   custom tokens extend the existing AnonymousToken and RememberMeToken.

Serializer
----------

 * Relying on the default value (false) of the "as_collection" option is deprecated since 4.2.
   You should set it to false explicitly instead as true will be the default value in 5.0.

Translation
-----------

 * The `TranslatorInterface` has been deprecated in favor of `Symfony\Contracts\Translation\TranslatorInterface`
 * The `MessageSelector`, `Interval` and `PluralizationRules` classes have been deprecated, use `IdentityTranslator` instead

Validator
---------

 * The component is now decoupled from `symfony/translation` and uses `Symfony\Contracts\Translation\TranslatorInterface` instead
 * The `ValidatorBuilderInterface` has been deprecated and `ValidatorBuilder` made final
 * Deprecated validating instances of `\DateTimeInterface` in `DateTimeValidator`, `DateValidator` and `TimeValidator`. Use `Type` instead or remove the constraint if the underlying model is type hinted to `\DateTimeInterface` already.
