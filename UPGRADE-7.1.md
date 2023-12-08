UPGRADE FROM 7.0 to 7.1
=======================

Cache
-----

 * Deprecate `CouchbaseBucketAdapter`, use `CouchbaseCollectionAdapter` instead

Messenger
---------

 * Make `#[AsMessageHandler]` final
 * Deprecate `HandleTrait`, use `SingleHandlingTrait` instead

Workflow
--------

 * Add method `getEnabledTransition()` to `WorkflowInterface`
