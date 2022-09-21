UPGRADE FROM 6.0 to 6.1
=======================

All components
--------------

 * Deprecate requiring the "symfony/symfony" package; replace it with standalone components instead
 * Public and protected properties are now considered final;
   instead of overriding a property, consider setting its value in the constructor

Console
-------

 * Deprecate `Command::$defaultName` and `Command::$defaultDescription`, use the `AsCommand` attribute instead
 * Add argument `$suggestedValues` to `Command::addArgument` and `Command::addOption`
 * Add argument `$suggestedValues` to `InputArgument` and `InputOption` constructors

DependencyInjection
-------------------

 * Deprecate `ReferenceSetArgumentTrait`

FrameworkBundle
---------------

 * Deprecate the `reset_on_message` config option. It can be set to `true` only and does nothing now.
   To prevent services resetting after each message the "--no-reset" option in "messenger:consume" command can be set
 * Deprecate not setting the `http_method_override` config option. The default value will change to `false` in 7.0.

HttpKernel
----------

 * Deprecate StreamedResponseListener, it's not needed anymore

Routing
-------

 * Add argument `$routeParameters` to `UrlMatcher::handleRouteRequirements()`

Serializer
----------

 * Deprecate `ContextAwareNormalizerInterface`, use `NormalizerInterface` instead
 * Deprecate `ContextAwareDenormalizerInterface`, use `DenormalizerInterface` instead
 * Deprecate supporting denormalization for `AbstractUid` in `UidNormalizer`, use one of `AbstractUid` child class instead
 * Deprecate denormalizing to an abstract class in `UidNormalizer`

Validator
---------

 * Deprecate `Constraint::$errorNames`, use `Constraint::ERROR_NAMES` instead
 * Deprecate constraint `ExpressionLanguageSyntax`, use `ExpressionSyntax` instead
 * Implementing the `ConstraintViolationInterface` or `ConstraintViolationListInterface`
   without implementing the `__toString()` method is deprecated
