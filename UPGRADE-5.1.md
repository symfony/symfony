UPGRADE FROM 5.0 to 5.1
=======================

FrameworkBundle
---------------

 * Marked `MicroKernelTrait::configureRoutes()` as `@internal` and `@final`.
 * Deprecated not overriding `MicroKernelTrait::configureRouting()`.

Routing
-------

 * Deprecated `RouteCollectionBuilder` in favor of `RoutingConfigurator`.
