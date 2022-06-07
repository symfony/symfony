UPGRADE FROM 6.x to 7.0
=======================

All components
--------------

 * Remove the "symfony/symfony" package; replace it with standalone components instead
 * Public and protected properties are now considered final;
   instead of overriding a property, set its value in the constructor

Console
-------

 * Remove `Command::$defaultName` and `Command::$defaultDescription`, use the `AsCommand` attribute instead
 * Add argument `$suggestedValues` to `Command::addArgument` and `Command::addOption`
 * Add argument `$suggestedValues` to `InputArgument` and `InputOption` constructors

DependencyInjection
-------------------

 * Remove `ReferenceSetArgumentTrait`

FrameworkBundle
---------------

 * Remove the `reset_on_message` config option. It can be set to `true` only and does nothing now
   To prevent services resetting after each message the "--no-reset" option in "messenger:consume" command can be set
 * Change the `http_method_override` config option default value to `false`

HttpKernel
----------

 * Remove StreamedResponseListener, it's not needed anymore

Routing
-------

 * Add argument `$routeParameters` to `UrlMatcher::handleRouteRequirements()`

Serializer
----------

 * Remove `ContextAwareNormalizerInterface`, use `NormalizerInterface` instead
 * Remove `ContextAwareDenormalizerInterface`, use `DenormalizerInterface` instead
 * Remove `ContextAwareEncoderInterface`, use `EncoderInterface` instead
 * Remove `ContextAwareDecoderInterface`, use `DecoderInterface` instead
 * Remove denormalization support for `AbstractUid` in `UidNormalizer`, use one of `AbstractUid` child class instead
 * Deprecate the ability to dernormalize to an abstract class in `UidNormalizer`

Validator
---------

 * Remove `Constraint::$errorNames`, use `Constraint::ERROR_NAMES` instead
 * Remove constraint `ExpressionLanguageSyntax`, use `ExpressionSyntax` instead
 * Add `__toString()` method as part of  `ConstraintViolationInterface` and `ConstraintViolationListInterface`
