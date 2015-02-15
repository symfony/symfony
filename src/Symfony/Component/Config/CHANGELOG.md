CHANGELOG
=========

<<<<<<< HEAD
2.2.0
-----

 * added ArrayNodeDefinition::canBeEnabled() and ArrayNodeDefinition::canBeDisabled()
=======
3.0.0
-----

 * removed `ReferenceDumper` class

2.2.0
-----

 * added `ArrayNodeDefinition::canBeEnabled()` and `ArrayNodeDefinition::canBeDisabled()`
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
   to ease configuration when some sections are respectively disabled / enabled
   by default.
 * added a `normalizeKeys()` method for array nodes (to avoid key normalization)
 * added numerical type handling for config definitions
<<<<<<< HEAD
 * added convenience methods for optional configuration sections to ArrayNodeDefinition
=======
 * added convenience methods for optional configuration sections to `ArrayNodeDefinition`
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
 * added a utils class for XML manipulations

2.1.0
-----

 * added a way to add documentation on configuration
 * implemented `Serializable` on resources
<<<<<<< HEAD
 * LoaderResolverInterface is now used instead of LoaderResolver for type
=======
 * `LoaderResolverInterface` is now used instead of `LoaderResolver` for type
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
   hinting
