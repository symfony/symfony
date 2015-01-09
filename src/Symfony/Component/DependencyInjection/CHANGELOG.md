CHANGELOG
=========

2.7.0
-----

 * deprecated synchronized services

2.6.0
-----

 * added new factory syntax and deprecated the old one

2.5.0
-----

* added DecoratorServicePass and a way to override a service definition (Definition::setDecoratedService())
* deprecated SimpleXMLElement class.

2.4.0
-----

 * added support for expressions in service definitions
 * added ContainerAwareTrait to add default container aware behavior to a class

2.2.0
-----

 * added Extension::isConfigEnabled() to ease working with enableable configurations
 * added an Extension base class with sensible defaults to be used in conjunction
   with the Config component.
 * added PrependExtensionInterface (to be able to allow extensions to prepend
   application configuration settings for any Bundle)

2.1.0
-----

 * added IntrospectableContainerInterface (to be able to check if a service
   has been initialized or not)
 * added ConfigurationExtensionInterface
 * added Definition::clearTag()
 * component exceptions that inherit base SPL classes are now used exclusively
   (this includes dumped containers)
 * [BC BREAK] fixed unescaping of class arguments, method
   ParameterBag::unescapeValue() was made public
