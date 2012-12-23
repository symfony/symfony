CHANGELOG
=========

2.2.0
-----

 * [BC BREAK] RouteCollection does not behave like a tree structure anymore but as
   a flat array of Routes. So when using PHP to build the RouteCollection, you must
   make sure to add routes to the sub-collection before adding it to the parent
   collection (this is not relevant when using YAML or XML for Route definitions).

   Before:

   ```
   $rootCollection = new RouteCollection();
   $subCollection = new RouteCollection();
   $rootCollection->addCollection($subCollection);
   $subCollection->add('foo', new Route('/foo'));
   ```

   After:

   ```
   $rootCollection = new RouteCollection();
   $subCollection = new RouteCollection();
   $subCollection->add('foo', new Route('/foo'));
   $rootCollection->addCollection($subCollection);
   ```

   Also one must call `addCollection` from the bottom to the top hierarchy.
   So the correct sequence is the following (and not the reverse):

   ```
   $childCollection->->addCollection($grandchildCollection);
   $rootCollection->addCollection($childCollection);
   ```

 * [DEPRECATION] The methods `RouteCollection::getParent()` and `RouteCollection::getRoot()`
   have been deprecated and will be removed in Symfony 2.3.
 * [BC BREAK] Misusing the `RouteCollection::addPrefix` method to add defaults, requirements
   or options without adding a prefix is not supported anymore. So if you called `addPrefix`
   with an empty prefix or `/` only (both have no relevance), like
   `addPrefix('', $defaultsArray, $requirementsArray, $optionsArray)`
   you need to use the new dedicated methods `addDefaults($defaultsArray)`,
   `addRequirements($requirementsArray)` or `addOptions($optionsArray)` instead.
 * [DEPRECATION] The `$options` parameter to `RouteCollection::addPrefix()` has been deprecated
   because adding options has nothing to do with adding a path prefix. If you want to add options
   to all child routes of a RouteCollection, you can use `addOptions()`.
 * [DEPRECATION] The method `RouteCollection::getPrefix()` has been deprecated
   because it suggested that all routes in the collection would have this prefix, which is
   not necessarily true. On top of that, since there is no tree structure anymore, this method
   is also useless. Don't worry about performance, prefix optimization for matching is still done
   in the dumper, which was also improved in 2.2.0 to find even more grouping possibilities.
 * [DEPRECATION] `RouteCollection::addCollection(RouteCollection $collection)` should now only be
   used with a single parameter. The other params `$prefix`, `$default`, `$requirements` and `$options`
   will still work, but have been deprecated. The `addPrefix` method should be used for this
   use-case instead.
   Before: `$parentCollection->addCollection($collection, '/prefix', array(...), array(...))`
   After:
   ```
   $collection->addPrefix('/prefix', array(...), array(...));
   $parentCollection->addCollection($collection);
   ```
 * added support for the method default argument values when defining a @Route
 * Adjacent placeholders without separator work now, e.g. `/{x}{y}{z}.{_format}`.
 * Characters that function as separator between placeholders are now whitelisted
   to fix routes with normal text around a variable, e.g. `/prefix{var}suffix`.
 * [BC BREAK] The default requirement of a variable has been changed slightly.
   Previously it disallowed the previous and the next char around a variable. Now
   it disallows the slash (`/`) and the next char. Using the previous char added
   no value and was problematic because the route `/index.{_format}` would be
   matched by `/index.ht/ml`.
 * The default requirement now uses possessive quantifiers when possible which
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
