CHANGELOG
=========

2.1.0
-----

 * added RequestMatcherInterface
 * added RequestContext::fromRequest()
 * the UrlMatcher does not throw a \LogicException anymore when the required
   scheme is not the current one
 * added TraceableUrlMatcher
 * added the possibility to define options, default values and requirements
   for placeholders in prefix, including imported routes
 * added RouterInterface::getRouteCollection
 * [BC BREAK] the UrlMatcher urldecodes the route parameters only once, they
   were decoded twice before. Note that the `urldecode()` calls have been
   changed for a single `rawurldecode()` in order to support `+` for input
   paths.
 * added RouteCollection::getRoot method to retrieve the root of a
   RouteCollection tree
 * [BC BREAK] made RouteCollection::setParent private which could not have
   been used anyway without creating inconsistencies
 * [BC BREAK] RouteCollection::remove also removes a route from parent
   collections (not only from its children)
 * added strict_parameters option to disable exceptions (and generate empty
   URLs instead) when generating a route with an invalid parameter value
