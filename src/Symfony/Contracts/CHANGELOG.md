CHANGELOG
=========

2.1.0
-----

 * added "symfony/deprecation-contracts"

2.0.1
-----

 * added `/json` endpoints to the test mock HTTP server

2.0.0
-----

 * bumped minimum PHP version to 7.2 and added explicit type hints
 * made "psr/event-dispatcher" a required dependency of "symfony/event-dispatcher-contracts"
 * made "symfony/http-client-contracts" not experimental anymore

1.1.0
-----

 * added `HttpClient` namespace with contracts for implementing flexible HTTP clients
 * added `EventDispatcherInterface` and `Event` in namespace `EventDispatcher`
 * added `ServiceProviderInterface` in namespace `Service`

1.0.0
-----

 * added `Service\ResetInterface` to provide a way to reset an object to its initial state
 * added `Translation\TranslatorInterface` and `Translation\TranslatorTrait`
 * added `Cache` contract to extend PSR-6 with tag invalidation, callback-based computation and stampede protection
 * added `Service\ServiceSubscriberInterface` to declare the dependencies of a class that consumes a service locator
 * added `Service\ServiceSubscriberTrait` to implement `Service\ServiceSubscriberInterface` using methods' return types
 * added `Service\ServiceLocatorTrait` to help implement PSR-11 service locators
