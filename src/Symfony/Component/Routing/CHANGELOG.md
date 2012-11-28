CHANGELOG
=========

2.2.0
-----

 * added support for the method default argument values when defining a @Route
 * Adjacent placeholders without separator work now, e.g. `/{x}{y}{z}.{_format}`.
 * Characters that function as separator between placeholders are now whitelisted
   to fix routes with normal text around a variable, e.g. `/prefix{var}suffix`.
 * [BC BREAK] The default requirement of a variable has been changed slightly.
   Previously it disallowed the previous and the next char around a variable. Now
   it disallows the slash (`/`) and the next char. Using the previous char added
   no value and was problematic because the route `/index.{_format}` would be
   matched by `/index.ht/ml`.
 * The default requirement now uses possesive quantifiers when possible which
   improves matching performance by up to 20% because it prevents backtracking
   when it's not needed.
 * The ConfigurableRequirementsInterface can now also be used to disable the requirements
   check on URL generation completely by calling `setStrictRequirements(null)`. It
   improves performance in production environment as you should know that params always
   pass the requirements (otherwise it would break your link anyway).
 * There is no restriction on the route name anymore. So non-alphanumeric characters
   are now also allowed.

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
 * added ConfigurableRequirementsInterface that allows to disable exceptions 
   (and generate empty URLs instead) when generating a route with an invalid
   parameter value
