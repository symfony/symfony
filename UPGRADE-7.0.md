UPGRADE FROM 6.4 to 7.0
=======================

Messenger
---------

 * Remove `Symfony\Component\Messenger\Transport\InMemoryTransport` and
   `Symfony\Component\Messenger\Transport\InMemoryTransportFactory` in favor of
   `Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport` and
   `Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory`

Workflow
--------

 * The first argument of `WorkflowDumpCommand` must be a `ServiceLocator` of all
   workflows indexed by names
