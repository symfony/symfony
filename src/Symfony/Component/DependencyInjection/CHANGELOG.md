CHANGELOG
=========

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

