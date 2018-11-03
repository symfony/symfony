UPGRADE FROM 4.1 to 4.2
=======================

BrowserKit
----------

 * The `Client::submit()` method will have a new `$serverParameters` argument in version 5.0, not defining it is deprecated.

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

DomCrawler
----------

 * The `Crawler::children()` method will have a new `$selector` argument in version 5.0, not defining it is deprecated.

Finder
------

 * The `Finder::sortByName()` method will have a new `$useNaturalSort` argument in version 5.0, not defining it is deprecated.

Form
----

 * The `getExtendedType()` method of the `FormTypeExtensionInterface` is deprecated and will be removed in 5.0. Type
   extensions must implement the static `getExtendedTypes()` method instead and return an iterable of extended types.

   Before:

   ```php
   class FooTypeExtension extends AbstractTypeExtension
   {
       public function getExtendedType()
       {
           return FormType::class;
       }

       // ...
   }
   ```

   After:

   ```php
   class FooTypeExtension extends AbstractTypeExtension
   {
       public static function getExtendedTypes(): iterable
       {
           return array(FormType::class);
       }

       // ...
   }
   ```
 * The `scale` option of the `IntegerType` is deprecated.
 * The `$scale` argument of the `IntegerToLocalizedStringTransformer` is deprecated.
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

 * The `regions` option of the `TimezoneType` is deprecated.

HttpFoundation
--------------

 * The default value of the "$secure" and "$samesite" arguments of Cookie's constructor
   will respectively change from "false" to "null" and from "null" to "lax" in Symfony
   5.0, you should define their values explicitly or use "Cookie::create()" instead.

HttpKernel
----------

 * The `Kernel::getRootDir()` and the `kernel.root_dir` parameter have been deprecated
 * The `KernelInterface::getName()` and the `kernel.name` parameter have been deprecated
 * Deprecated the first and second constructor argument of `ConfigDataCollector` 
 * Deprecated `ConfigDataCollector::getApplicationName()` 
 * Deprecated `ConfigDataCollector::getApplicationVersion()`

FrameworkBundle
---------------

 * The `allow_no_handler` middleware has been removed. Use `framework.messenger.buses.[bus_id].default_middleware` instead:

   Before:
   ```yaml
   framework:
       messenger:
           buses:
               messenger.bus.events:
                   middleware:
                     - allow_no_handler
   ```

   After:
   ```yaml
   framework:
       messenger:
           buses:
               messenger.bus.events:
                   default_middleware: allow_no_handlers
   ```

 * The `messenger:consume-messages` command expects a mandatory `--bus` option value if you have more than one bus configured.
 * The `framework.router.utf8` configuration option has been added. If your app's charset
   is UTF-8 (see kernel's `getCharset()` method), it is recommended to set it to `true`:
   this will generate 404s for non-UTF-8 URLs, which are incompatible with you app anyway,
   and will allow dumping optimized routers and using Unicode classes in requirements.
 * Added support for the SameSite attribute for session cookies. It is highly recommended to set this setting (`framework.session.cookie_samesite`) to `lax` for increased security against CSRF attacks.
 * The `Controller` class has been deprecated, use `AbstractController` instead.
 * The Messenger encoder/decoder configuration has been changed for a unified Messenger serializer configuration.

   Before:
   ```yaml
   framework:
       messenger:
           encoder: your_encoder_service_id
           decoder: your_decoder_service_id
   ```

   After:
   ```yaml
   framework:
       messenger:
           serializer:
               id: your_messenger_service_id
   ```
 * The `ContainerAwareCommand` class has been deprecated, use `Symfony\Component\Console\Command\Command`
   with dependency injection instead.
 * The `--env` console option and its "-e" shortcut have been deprecated,
   set the "APP_ENV" environment variable instead.
 * The `--no-debug` console option has been deprecated,
   set the "APP_DEBUG" environment variable to "0" instead.
 * The `Templating\Helper\TranslatorHelper::transChoice()` method has been deprecated, use the `trans()` one instead with a `%count%` parameter.
 * Deprecated support for legacy translations directories `src/Resources/translations/` and `src/Resources/<BundleName>/translations/`, use `translations/` instead.
 * Support for the legacy directory structure in `translation:update` and `debug:translation` commands has been deprecated.

Messenger
---------

 * The `MiddlewareInterface::handle()` and `SenderInterface::send()` methods must now return an `Envelope` instance.
 * The return value of handlers is ignored. If you used to return a value, e.g in query bus handlers, you can either:
    - make your `Query` mutable to allow setting & getting a result:
      ```php
      // When dispatching:
      $bus->dispatch($query = new Query());
      $result = $query->getResult();

      // In your handler:
      $query->setResult($yourResult);
      ```
    - define a callable on your `Query` to be called in your handler:
      ```php
      // When dispatching:
      $bus->dispatch(new Query([$this, 'onResult']));

      // In your handler:
      $query->executeCallback($yourResult);
      ```

 * The `EnvelopeAwareInterface` was removed and the `MiddlewareInterface::handle()` method now requires an `Envelope` object
   as first argument. When using built-in middleware with the provided `MessageBus`, you will not have to do anything.  
   If you use your own `MessageBusInterface` implementation, you must wrap the message in an `Envelope` before passing it to middleware.  
   If you created your own middleware, you must change the signature to always expect an `Envelope`.
 * The `MiddlewareInterface::handle()` second argument (`callable $next`) has changed in favor of a `StackInterface` instance.
   When using built-in middleware with the provided `MessageBus`, you will not have to do anything.  
   If you use your own `MessageBusInterface` implementation, you can use the `StackMiddleware` implementation.  
   If you created your own middleware, you must change the signature to always expect an `StackInterface` instance
   and call `$stack->next()->handle($envelope, $stack)` instead of `$next` to call the next middleware:
   
   Before:
   ```php
   public function handle($message, callable $next): Envelope
   {
       // do something before
       $message = $next($message);
       // do something after
    
       return $message;
   }
   ```

   After:
   ```php
   public function handle(Envelope $envelope, StackInterface $stack): Envelope
   {
       // do something before
       $envelope = $stack->next()->handle($envelope, $stack);
       // do something after
    
       return $envelope;
   }
   ```
 * `StampInterface` replaces `EnvelopeItemInterface` and doesn't extend `Serializable` anymore.
    Built-in `ReceivedMessage`, `ValidationConfiguration` and `SerializerConfiguration` were renamed
    respectively `ReceivedStamp`, `ValidationStamp`, `SerializerStamp` and moved to the `Stamp` namespace.
 * `AllowNoHandlerMiddleware` has been removed in favor of a new constructor argument on `HandleMessageMiddleware`
 * The `ConsumeMessagesCommand` class now takes an instance of `Psr\Container\ContainerInterface`
    as first constructor argument, i.e a message bus locator. The CLI command now expects a mandatory 
    `--bus` option value if there is more than one bus in the locator.
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
   yield SecondMessage::class => ['priority' => -10];
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
 * The `EncoderInterface` and `DecoderInterface` interfaces have been replaced by a unified `Symfony\Component\Messenger\Transport\Serialization\SerializerInterface`.
   Each interface method have been merged untouched into the `Serializer` interface, so you can simply merge your two implementations together and implement the new interface.

Monolog
-------

 * The methods `DebugProcessor::getLogs()`, `DebugProcessor::countErrors()`, `Logger::getLogs()` and `Logger::countErrors()` will have a new `$request` argument in version 5.0, not defining it is deprecated.

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
 * `SimpleAuthenticatorInterface`, `SimpleFormAuthenticatorInterface`, `SimplePreAuthenticatorInterface`,
   `SimpleAuthenticationProvider`, `SimpleAuthenticationHandler`, `SimpleFormAuthenticationListener` and
   `SimplePreAuthenticationListener` have been deprecated. Use Guard instead.

SecurityBundle
--------------

 * Passing a `FirewallConfig` instance as 3rd argument to the `FirewallContext` constructor is deprecated,
   pass a `LogoutListener` instance instead.
 * Using the `security.authentication.trust_resolver.anonymous_class` and
   `security.authentication.trust_resolver.rememberme_class` parameters to define
   the token classes is deprecated. To use
   custom tokens extend the existing AnonymousToken and RememberMeToken.
 * The `simple_form` and `simple_preauth` authentication listeners have been deprecated,
   use Guard instead.
 * The `SimpleFormFactory` and `SimplePreAuthenticationFactory` classes have been deprecated,
   use Guard instead.

Serializer
----------

 * Relying on the default value (false) of the "as_collection" option is deprecated.
   You should set it to false explicitly instead as true will be the default value in 5.0.
 * The `AbstractNormalizer::handleCircularReference()` method will have two new `$format` and `$context` arguments in version 5.0, not defining them is deprecated.

Translation
-----------

 * The `TranslatorInterface` has been deprecated in favor of `Symfony\Contracts\Translation\TranslatorInterface`
 * The `Translator::transChoice()` method has been deprecated in favor of using `Translator::trans()` with "%count%" as the parameter driving plurals
 * The `MessageSelector`, `Interval` and `PluralizationRules` classes have been deprecated, use `IdentityTranslator` instead
 * The `Translator::getFallbackLocales()` and `TranslationDataCollector::getFallbackLocales()` method have been marked as internal

TwigBundle
----------

 * The `transchoice` tag and filter have been deprecated, use the `trans` ones instead with a `%count%` parameter.
 * Deprecated support for legacy templates directories `src/Resources/views/` and `src/Resources/<BundleName>/views/`, use `templates/` and `templates/bundles/<BundleName>/` instead.

Validator
---------

 * The `checkMX` and `checkHost` options of the `Email` constraint are deprecated
 * The component is now decoupled from `symfony/translation` and uses `Symfony\Contracts\Translation\TranslatorInterface` instead
 * The `ValidatorBuilderInterface` has been deprecated and `ValidatorBuilder` made final
 * Deprecated validating instances of `\DateTimeInterface` in `DateTimeValidator`, `DateValidator` and `TimeValidator`. Use `Type` instead or remove the constraint if the underlying model is type hinted to `\DateTimeInterface` already.
 * Using the `Bic`, `Country`, `Currency`, `Language` and `Locale` constraints without `symfony/intl` is deprecated
 * Using the `Email` constraint without `egulias/email-validator` is deprecated
 * Using the `Expression` constraint without `symfony/expression-language` is deprecated

WebServerBundle
---------------

* Omitting the `$environment` argument of the `ServerRunCommand` and 
  `ServerStartCommand` constructors is deprecated.
