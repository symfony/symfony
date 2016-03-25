UPGRADE FROM 5.x to 6.0
=======================

Console
-------

 * `Command::setHidden()` has a default value (`true`) for `$hidden` parameter

Dotenv
------

 * Removed argument `$usePutenv` from Dotenv's constructor, use `Dotenv::usePutenv()` instead.

EventDispatcher
---------------

 * Removed `LegacyEventDispatcherProxy`. Use the event dispatcher without the proxy.

Form
----

 * Added the `getIsEmptyCallback()` method to the `FormConfigInterface`.
 * Added the `setIsEmptyCallback()` method to the `FormConfigBuilderInterface`.
 * Added argument `callable|null $filter` to `ChoiceListFactoryInterface::createListFromChoices()` and `createListFromLoader()`.
 * Usage of `choice_attr` option as an array of nested arrays has been removed
   and indexes are considered as attributes. Use a unique array for all choices or a `callable` instead.

   Before:
   ```php
   // Single array for all choices using callable
   'choice_attr' => function () {
       return ['class' => 'choice-options'];
   },

   // Different arrays per choice using array
   'choices' => [
       'Yes' => true,
       'No' => false,
       'Maybe' => null,
   ],
   'choice_attr' => [
       'Yes' => ['class' => 'option-green'],
       'No' => ['class' => 'option-red'],
   ],
   ```

   After:
   ```php
   // Single array for all choices using array
   'choice_attr' => ['class' => 'choice-options'],

   // Different arrays per choice using callable
   'choices' => [
       'Yes' => true,
       'No' => false,
       'Maybe' => null,
   ],
   'choice_attr' => function ($choice, $index, $value) {
       if ('Yes' === $index) {
           return ['class' => 'option-green'];
       }
       if ('No' === $index) {
           return ['class' => 'option-red'];
       }

       return [];
   },
   ```

FrameworkBundle
---------------

 * `MicroKernelTrait::configureRoutes()` is now always called with a `RoutingConfigurator`
 * The "framework.router.utf8" configuration option defaults to `true`

HttpFoundation
--------------

 * Removed `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, and `StreamedResponse::create()` methods (use
   `__construct()` instead)

Messenger
---------

 * Removed AmqpExt transport. Run `composer require symfony/amqp-messenger` to keep the transport in your application.
 * Removed Doctrine transport. Run `composer require symfony/doctrine-messenger` to keep the transport in your application.
 * Removed RedisExt transport. Run `composer require symfony/redis-messenger` to keep the transport in your application.
 * Use of invalid options in Redis and AMQP connections now throws an error.

PhpUnitBridge
-------------

 * Removed support for `@expectedDeprecation` annotations, use the `ExpectDeprecationTrait::expectDeprecation()` method instead.

Routing
-------

 * Removed `RouteCollectionBuilder`.
 * Added argument `$priority` to `RouteCollection::add()`
 * Removed the `RouteCompiler::REGEX_DELIMITER` constant

Security
--------

 * Removed `ROLE_PREVIOUS_ADMIN` role in favor of `IS_IMPERSONATOR` attribute
