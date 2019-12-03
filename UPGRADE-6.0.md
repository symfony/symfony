UPGRADE FROM 5.x to 6.0
=======================

FrameworkBundle
---------------

 * Removed `MicroKernelTrait::configureRoutes()`.
 * Made `MicroKernelTrait::configureRouting()` abstract.

HttpFoundation
--------------

 * Removed `Response::create()`, `JsonResponse::create()`,
   `RedirectResponse::create()`, and `StreamedResponse::create()` methods (use
   `__construct()` instead)

Routing
-------

 * Removed `RouteCollectionBuilder`.
