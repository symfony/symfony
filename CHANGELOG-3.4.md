CHANGELOG for 3.4.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 3.4 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v3.4.0...v3.4.1

* 3.4.20 (2018-12-06)

 * security #cve-2018-19790 [Security\Http] detect bad redirect targets using backslashes (xabbuh)
 * security #cve-2018-19789 [Form] Filter file uploads out of regular form types (nicolas-grekas)
 * bug #29436 [Cache] Fixed Memcached adapter doClear()to call flush() (raitocz)
 * bug #29441 [Routing] ignore trailing slash for non-GET requests (nicolas-grekas)
 * bug #29432 [DI] dont inline when lazy edges are found (nicolas-grekas)
 * bug #29413 [Serializer] fixed DateTimeNormalizer to maintain microseconds when a different timezone required (rvitaliy)
 * bug #29424 [Routing] fix taking verb into account when redirecting (nicolas-grekas)
 * bug #29414 [DI] Fix dumping expressions accessing single-use private services (chalasr)
 * bug #29375 [Validator] Allow `ConstraintViolation::__toString()` to expose codes that are not null or emtpy strings (phansys)
 * bug #29376 [EventDispatcher] Fix eventListener wrapper loop in TraceableEventDispatcher (jderusse)
 * bug #29343 [Form] Handle all case variants of "nan" when parsing a number (mwhudson, xabbuh)
 * bug #29355 [PropertyAccess] calculate cache keys for property setters depending on the value (xabbuh)
 * bug #29369 [DI] fix combinatorial explosion when analyzing the service graph (nicolas-grekas)
 * bug #29349 [Debug] workaround opcache bug mutating "$this" !?! (nicolas-grekas)
