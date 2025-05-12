UPGRADE FROM 7.2 to 7.3
=======================

Symfony 7.3 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.3/setup/upgrade_minor.html).

If you're upgrading from a version below 7.2, follow the [7.2 upgrade guide](UPGRADE-7.2.md) first.

AssetMapper
-----------

 * `ImportMapRequireCommand` now takes `projectDir` as a required third constructor argument

Console
-------

 * Omitting parameter types or returning a non-integer value from a `\Closure` set via `Command::setCode()` method is deprecated

   Before:

   ```php
   $command->setCode(function ($input, $output) {
       // ...
   });
   ```

   After:

   ```php
   use Symfony\Component\Console\Input\InputInterface;
   use Symfony\Component\Console\Output\OutputInterface;

   $command->setCode(function (InputInterface $input, OutputInterface $output): int {
       // ...

       return 0;
   });
   ```

 * Deprecate methods `Command::getDefaultName()` and `Command::getDefaultDescription()` in favor of the `#[AsCommand]` attribute
 * `#[AsCommand]` attribute is now marked as `@final`; you should use separate attributes to add more logic to commands

DependencyInjection
-------------------

 * Deprecate `ContainerBuilder::getAutoconfiguredAttributes()` in favor of the `getAttributeAutoconfigurators()` method.

DoctrineBridge
--------------

 * Deprecate the `DoctrineExtractor::getTypes()` method, use `DoctrineExtractor::getType()` instead

FrameworkBundle
---------------

 * Not setting the `framework.property_info.with_constructor_extractor` option explicitly is deprecated
   because its default value will change in version 8.0
 * Deprecate the `--show-arguments` option of the `container:debug` command, as arguments are now always shown
 * Deprecate the `framework.validation.cache` config option
 * Deprecate the `RateLimiterFactory` autowiring aliases, use `RateLimiterFactoryInterface` instead
 * Deprecate setting the `framework.profiler.collect_serializer_data` config option to `false`

   When set to `true`, normalizers must be injected using the `NormalizerInterface`, and not using any concrete implementation.

   Before:

   ```php
   public function __construct(ObjectNormalizer $normalizer) {}
   ```

   After:

   ```php
   public function __construct(#[Autowire('@serializer.normalizer.object')] NormalizerInterface $normalizer) {}
   ```

 * The XML routing configuration files (`errors.xml` and `webhook.xml`) are
   deprecated, use their PHP equivalent ones:

   Before:

   ```yaml
   when@dev:
       _errors:
           resource: '@FrameworkBundle/Resources/config/routing/errors.xml'
           prefix: /_error

   webhook:
       resource: '@FrameworkBundle/Resources/config/routing/webhook.xml'
       prefix: /webhook
   ```

   After:

   ```yaml
   when@dev:
       _errors:
           resource: '@FrameworkBundle/Resources/config/routing/errors.php'
           prefix: /_error

   webhook:
       resource: '@FrameworkBundle/Resources/config/routing/webhook.php'
       prefix: /webhook
   ```

HttpFoundation
--------------

 * `Request::getPreferredLanguage()` now favors a more preferred language above exactly matching a locale

Ldap
----

 * Deprecate `LdapUser::eraseCredentials()` in favor of `__serialize()`

OptionsResolver
---------------

 * Deprecate defining nested options via `setDefault()`, use `setOptions()` instead

  *Before*
  ```php
  $resolver->setDefault('option', function (OptionsResolver $resolver) {
      // ...
  });
  ```

  *After*
  ```php
  $resolver->setOptions('option', function (OptionsResolver $resolver) {
      // ...
  });
  ```

PropertyInfo
------------

 * Deprecate the `Type` class, use `Symfony\Component\TypeInfo\Type` class from `symfony/type-info` instead
 * Deprecate the `PropertyTypeExtractorInterface::getTypes()` method, use `PropertyTypeExtractorInterface::getType()` instead
 * Deprecate the `ConstructorArgumentTypeExtractorInterface::getTypesFromConstructor()` method, use `ConstructorArgumentTypeExtractorInterface::getTypeFromConstructor()` instead

Security
--------

 * Deprecate `UserInterface::eraseCredentials()` and `TokenInterface::eraseCredentials()`;
   erase credentials e.g. using `__serialize()` instead

   Before:

   ```php
   public function eraseCredentials(): void
   {
   }
   ```

   After:

   ```php
   #[\Deprecated]
   public function eraseCredentials(): void
   {
   }

   // If your eraseCredentials() method was used to empty a "password" property:
   public function __serialize(): array
   {
       $data = (array) $this;
       unset($data["\0".self::class."\0password"]);

       return $data;
   }
   ```

 * Add argument `$vote` to `VoterInterface::vote()` and to `Voter::voteOnAttribute()`;
   it should be used to report the reason of a vote. E.g:

   ```php
   protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
   {
       $vote ??= new Vote();

       $vote->reasons[] = 'A brief explanation of why access is granted or denied, as appropriate.';
   }
   ```

 * Add argument `$accessDecision` to `AccessDecisionManagerInterface::decide()` and `AuthorizationCheckerInterface::isGranted()`;
   it should be used to report the reason of a decision, including all the related votes.

 * Add discovery support to `OidcTokenHandler` and `OidcUserInfoTokenHandler`

SecurityBundle
--------------

 * Deprecate the `security.hide_user_not_found` config option in favor of `security.expose_security_errors`

 Notifier
 --------

 * Deprecate the `Sms77` transport, use `SevenIo` instead

Serializer
----------

 * Deprecate the `CompiledClassMetadataFactory` and `CompiledClassMetadataCacheWarmer` classes

TypeInfo
--------

 * Deprecate constructing a `CollectionType` instance as a list that is not an array
 * Deprecate the third `$asList` argument of `TypeFactoryTrait::iterable()`, use `TypeFactoryTrait::list()` instead

Validator
---------

 * Deprecate defining custom constraints not supporting named arguments

   Before:

   ```php
   use Symfony\Component\Validator\Constraint;

   class CustomConstraint extends Constraint
   {
       public function __construct(array $options)
       {
           // ...
       }
   }
   ```

   After:

   ```php
   use Symfony\Component\Validator\Attribute\HasNamedArguments;
   use Symfony\Component\Validator\Constraint;

   class CustomConstraint extends Constraint
   {
       #[HasNamedArguments]
       public function __construct($option1, $option2, $groups, $payload)
       {
           // ...
       }
   }
   ```

 * Deprecate passing an array of options to the constructors of the constraint classes, pass each option as a dedicated argument instead

   Before:

   ```php
   new NotNull([
       'groups' => ['foo', 'bar'],
       'message' => 'a custom constraint violation message',
   ])
   ```

   After:

   ```php
   new NotNull(
       groups: ['foo', 'bar'],
       message: 'a custom constraint violation message',
   )
   ```

VarDumper
---------

 * Deprecate `ResourceCaster::castCurl()`, `ResourceCaster::castGd()` and `ResourceCaster::castOpensslX509()`
 * Mark all casters as `@internal`

VarExporter
-----------

 * Deprecate using `ProxyHelper::generateLazyProxy()` when native lazy proxies can be used - the method should be used to generate abstraction-based lazy decorators only
 * Deprecate `LazyGhostTrait` and `LazyProxyTrait`, use native lazy objects instead
 * Deprecate `ProxyHelper::generateLazyGhost()`, use native lazy objects instead

WebProfilerBundle
-----------------

 * The XML routing configuration files (`profiler.xml` and `wdt.xml`) are
   deprecated, use their PHP equivalent ones:

   Before:

   ```yaml
   when@dev:
       web_profiler_wdt:
           resource: '@WebProfilerBundle/Resources/config/routing/wdt.xml'
           prefix: /_wdt

       web_profiler_profiler:
           resource: '@WebProfilerBundle/Resources/config/routing/profiler.xml'
           prefix: /_profiler
   ```

   After:

   ```yaml
   when@dev:
       web_profiler_wdt:
           resource: '@WebProfilerBundle/Resources/config/routing/wdt.php'
           prefix: /_wdt

       web_profiler_profiler:
           resource: '@WebProfilerBundle/Resources/config/routing/profiler.php
           prefix: /_profiler
   ```

Workflow
--------

 * Deprecate `Event::getWorkflow()` method

   Before:

   ```php
   use Symfony\Component\Workflow\Attribute\AsCompletedListener;
   use Symfony\Component\Workflow\Event\CompletedEvent;

   class MyListener
   {
       #[AsCompletedListener('my_workflow', 'to_state2')]
       public function terminateOrder(CompletedEvent $event): void
       {
           $subject = $event->getSubject();
           if ($event->getWorkflow()->can($subject, 'to_state3')) {
               $event->getWorkflow()->apply($subject, 'to_state3');
           }
       }
   }
   ```

   After:

   ```php
   use Symfony\Component\DependencyInjection\Attribute\Target;
   use Symfony\Component\Workflow\Attribute\AsCompletedListener;
   use Symfony\Component\Workflow\Event\CompletedEvent;
   use Symfony\Component\Workflow\WorkflowInterface;

   class MyListener
   {
       public function __construct(
           #[Target('your_workflow_name')]
           private readonly WorkflowInterface $workflow,
       ) {
       }

       #[AsCompletedListener('your_workflow_name', 'to_state2')]
       public function terminateOrder(CompletedEvent $event): void
       {
           $subject = $event->getSubject();
           if ($this->workflow->can($subject, 'to_state3')) {
               $this->workflow->apply($subject, 'to_state3');
           }
       }
   }
   ```

   Or:

   ```php
   use Symfony\Component\DependencyInjection\ServiceLocator;
   use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
   use Symfony\Component\Workflow\Attribute\AsTransitionListener;
   use Symfony\Component\Workflow\Event\TransitionEvent;

   class GenericListener
   {
       public function __construct(
           #[AutowireLocator('workflow', 'name')]
           private ServiceLocator $workflows
       ) {
       }

       #[AsTransitionListener()]
       public function doSomething(TransitionEvent $event): void
       {
           $workflow = $this->workflows->get($event->getWorkflowName());
       }
   }
   ```
