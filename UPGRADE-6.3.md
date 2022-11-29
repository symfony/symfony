UPGRADE FROM 6.2 to 6.3
=======================

DependencyInjection
-------------------

 * Deprecate `PhpDumper` options `inline_factories_parameter` and `inline_class_loader_parameter`, use `inline_factories` and `inline_class_loader` instead
 * Deprecate undefined and numeric keys with `service_locator` config, use string aliases instead

FrameworkBundle
---------------

 * Deprecate `framework:exceptions` tag, unwrap it and replace `framework:exception` tags' `name` attribute by `class`

   Before:
   ```xml
   <!-- config/packages/framework.xml -->
   <framework:config>
       <framework:exceptions>
           <framework:exception
               name="Symfony\Component\HttpKernel\Exception\BadRequestHttpException"
               log-level="info"
               status-code="422"
           />
       </framework:exceptions>
   </framework:config>
   ```

   After:
   ```xml
   <!-- config/packages/framework.xml -->
   <framework:config>
       <framework:exception
           class="Symfony\Component\HttpKernel\Exception\BadRequestHttpException"
           log-level="info"
           status-code="422"
       />
   </framework:config>
   ```

HttpKernel
----------

 * Deprecate parameters `container.dumper.inline_factories` and `container.dumper.inline_class_loader`, use `.container.dumper.inline_factories` and `.container.dumper.inline_class_loader` instead

Messenger
---------

 * Deprecate `Symfony\Component\Messenger\Transport\InMemoryTransport` and
   `Symfony\Component\Messenger\Transport\InMemoryTransportFactory` in favor of
   `Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport` and
   `Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory`

SecurityBundle
--------------

 * Deprecate enabling bundle and not configuring it


Validator
--------------

 * Implementing the `ConstraintViolationInterface` without implementing the `getConstraint()` method is deprecated
 * Deprecate the `security.firewalls.logout.csrf_token_generator` config option, use `security.firewalls.logout.csrf_token_manager` instead
