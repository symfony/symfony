UPGRADE FROM 6.0 to 6.1
=======================

All components
--------------

 * Public and protected properties are now considered final;
   instead of overriding a property, consider setting its value in the constructor

Console
-------

 * Deprecate `Command::$defaultName` and `Command::$defaultDescription`, use the `AsCommand` attribute instead

Serializer
----------

 * Deprecate `ContextAwareNormalizerInterface`, use `NormalizerInterface` instead
 * Deprecate `ContextAwareDenormalizerInterface`, use `DenormalizerInterface` instead
 * Deprecate `ContextAwareEncoderInterface`, use `EncoderInterface` instead
 * Deprecate `ContextAwareDecoderInterface`, use `DecoderInterface` instead

Validator
---------

 * Deprecate `Constraint::$errorNames`, use `Constraint::ERROR_NAMES` instead
