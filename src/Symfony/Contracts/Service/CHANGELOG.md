CHANGELOG
=========

1.1.0
-----

 * added `ServiceProviderInterface` in namespace `Service`

1.0.0
-----

 * added `Service\ResetInterface` to provide a way to reset an object to its initial state
 * added `Service\ServiceSubscriberInterface` to declare the dependencies of a class that consumes a service locator
 * added `Service\ServiceSubscriberTrait` to implement `Service\ServiceSubscriberInterface` using methods' return types
 * added `Service\ServiceLocatorTrait` to help implement PSR-11 service locators
