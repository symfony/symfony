UPGRADE FROM 6.4 to 7.0
=======================

Symfony 6.4 and Symfony 7.0 will be released simultaneously at the end of November 2023. According to the Symfony
release process, both versions will have the same features, but Symfony 7.0 won't include any deprecated features.
To upgrade, make sure to resolve all deprecation notices.

Serializer
----------

 * Values being denormalized into `list` typed properties must be list themselves, otherwise the property must be typed with `array`

Workflow
--------

 * The first argument of `WorkflowDumpCommand` must be a `ServiceLocator` of all
   workflows indexed by names

This file will be updated on the branch 7.0 for each deprecated feature that is removed.
