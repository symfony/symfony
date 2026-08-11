<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\WhenClassExists;
use Symfony\Component\DependencyInjection\Attribute\WhenClassMissing;
use Symfony\Component\DependencyInjection\Attribute\WhenMissingService;
use Symfony\Component\DependencyInjection\Attribute\WhenParameter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveConditionalDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;

class ResolveConditionalDefinitionsPassTest extends TestCase
{
    public function testClassExistsConditionMatches()
    {
        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenClassExists(\stdClass::class)]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceKept($container, 'foo');
    }

    public function testClassExistsConditionFails()
    {
        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenClassExists('App\NotAClass')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceExcluded($container, 'foo', 'because the "App\NotAClass" class is not available');
    }

    public function testClassMissingConditionMatches()
    {
        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenClassMissing('App\NotAClass')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceKept($container, 'foo');
    }

    public function testClassMissingConditionFails()
    {
        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenClassMissing(\stdClass::class)]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceExcluded($container, 'foo', 'because the "stdClass" class is available');
    }

    public function testParameterConditionMatches()
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.enabled', true);
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenParameter('app.enabled')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceKept($container, 'foo');
    }

    public function testParameterConditionMatchesResolvedPlaceholder()
    {
        $container = new ContainerBuilder();
        $container->setParameter('flag', 'on');
        $container->setParameter('app.mode', '%flag%');
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenParameter('app.mode', 'on')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceKept($container, 'foo');
    }

    public function testParameterConditionFailsOnValueMismatch()
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.enabled', false);
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenParameter('app.enabled')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceExcluded($container, 'foo', 'because the "app.enabled" parameter does not have the expected value');
    }

    public function testParameterConditionFailsOnMissingParameter()
    {
        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenParameter('app.enabled')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceExcluded($container, 'foo', 'because the "app.enabled" parameter is not defined');
    }

    public function testParameterConditionThrowsOnEnvBackedParameter()
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.enabled', '%env(bool:APP_ENABLED)%');
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenParameter('app.enabled')]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('references an environment variable; its value is not known at compile time. Use a build-time parameter instead, or register the service conditionally in a compiler pass.');

        (new ResolveConditionalDefinitionsPass())->process($container);
    }

    public function testMissingServiceConditionFailsWhenServiceExists()
    {
        $container = new ContainerBuilder();
        $container->register('bar', \stdClass::class);
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenMissingService('bar')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceExcluded($container, 'foo', 'because the "bar" service is already defined');
    }

    public function testMissingServiceConditionMatchesWhenServiceIsMissing()
    {
        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenMissingService('bar')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceKept($container, 'foo');
    }

    public function testMissingServiceConditionFailsWhenAliasExists()
    {
        $container = new ContainerBuilder();
        $container->register('bar', \stdClass::class);
        $container->setAlias('bar_alias', 'bar');
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenMissingService('bar_alias')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceExcluded($container, 'foo', 'because the "bar_alias" service is already defined');
    }

    public function testMissingServiceConditionIgnoresAbstractAndExcludedTargets()
    {
        $container = new ContainerBuilder();
        $container->register('abstract_bar', \stdClass::class)->setAbstract(true);
        $container->register('excluded_bar', \stdClass::class)->addTag('container.excluded');
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenMissingService('abstract_bar'), new WhenMissingService('excluded_bar')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceKept($container, 'foo');
    }

    public function testMissingServiceConditionIgnoresAliasToItself()
    {
        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenMissingService('foo_interface')]);
        $container->setAlias('foo_interface', 'foo');

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceKept($container, 'foo');
        $this->assertTrue($container->hasAlias('foo_interface'));
    }

    public function testMissingServiceConditionOnAnotherConditionalServiceThrows()
    {
        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenMissingService('bar')]);
        $container->register('bar', \stdClass::class)
            ->setWhenConditions([new WhenMissingService('foo')]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Conditional services cannot depend on each other\'s absence.');

        (new ResolveConditionalDefinitionsPass())->process($container);
    }

    public function testMissingServiceConditionSeesStageOneRejections()
    {
        $container = new ContainerBuilder();
        $container->register('bar', \stdClass::class)
            ->setWhenConditions([new WhenClassExists('App\NotAClass')]);
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenMissingService('bar')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceExcluded($container, 'bar', 'because the "App\NotAClass" class is not available');
        $this->assertServiceKept($container, 'foo');
    }

    public function testConditionsAreAndCombined()
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.enabled', false);
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenClassExists(\stdClass::class), new WhenParameter('app.enabled')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceExcluded($container, 'foo', 'because the "app.enabled" parameter does not have the expected value');
    }

    public function testAliasesToExcludedServicesAreRemoved()
    {
        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenClassExists('App\NotAClass')]);
        $container->setAlias('foo_alias', 'foo');
        $container->setAlias('foo_alias_alias', new Alias('foo_alias'));

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertFalse($container->hasAlias('foo_alias'));
        $this->assertFalse($container->hasAlias('foo_alias_alias'));
    }

    public function testCompiledContainerExcludesRejectedDefinitions()
    {
        $container = new ContainerBuilder();
        $container->register('default_manager', \stdClass::class)
            ->setPublic(true)
            ->setWhenConditions([new WhenMissingService('manager')]);
        $container->register('manager', \stdClass::class)->setPublic(true);

        $container->compile();

        $this->assertTrue($container->has('manager'));
        $this->assertFalse($container->has('default_manager'));
    }

    public function testCompiledContainerKeepsDefaultWhenNothingOverridesIt()
    {
        $container = new ContainerBuilder();
        $container->register('default_manager', \stdClass::class)
            ->setPublic(true)
            ->setWhenConditions([new WhenMissingService('manager')]);

        $container->compile();

        $this->assertTrue($container->has('default_manager'));
    }

    public function testRejectedAutoconfiguredDefinitionDoesNotResurrect()
    {
        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(ConditionalHandlerInterface::class)
            ->addTag('app.handler');
        $container->register('conditional', ConditionalHandler::class)
            ->setAutoconfigured(true)
            ->setPublic(true)
            ->setWhenConditions([new WhenClassExists('App\NotAClass')]);

        $container->compile();

        $this->assertFalse($container->has('conditional'));
        $this->assertSame([], $container->findTaggedServiceIds('app.handler'));
    }

    public function testAutoconfiguredDefinitionRejectedByMissingServiceDoesNotResurrect()
    {
        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(ConditionalHandlerInterface::class)
            ->addTag('app.handler');
        $container->register('existing', \stdClass::class)->setPublic(true);
        $container->register('conditional', ConditionalHandler::class)
            ->setAutoconfigured(true)
            ->setPublic(true)
            ->setWhenConditions([new WhenMissingService('existing')]);

        $container->compile();

        $this->assertFalse($container->has('conditional'));
        $this->assertSame([], $container->findTaggedServiceIds('app.handler'));
    }

    public function testWhenConditionsAreNotInheritedByChildDefinitions()
    {
        $container = new ContainerBuilder();
        $container->register('parent_def', \stdClass::class)
            ->setWhenConditions([new WhenClassExists('App\NotAClass')]);
        $container->setDefinition('child', (new ChildDefinition('parent_def'))->setPublic(true));

        $container->compile();

        $this->assertFalse($container->has('parent_def'));
        $this->assertInstanceOf(\stdClass::class, $container->get('child'));
    }

    public function testKeptAutoconfiguredDefinitionIsStillAutoconfigured()
    {
        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(ConditionalHandlerInterface::class)
            ->addTag('app.handler');
        $container->register('conditional', ConditionalHandler::class)
            ->setAutoconfigured(true)
            ->setPublic(true)
            ->setWhenConditions([new WhenClassExists(\stdClass::class)]);

        $container->compile();

        $this->assertTrue($container->has('conditional'));
        $this->assertSame(['conditional'], array_keys($container->findTaggedServiceIds('app.handler')));
    }

    public function testStage1RejectedTargetWithOwnMissingServiceConditionDoesNotThrow()
    {
        $container = new ContainerBuilder();
        $container->register('bar', \stdClass::class)
            ->setWhenConditions([new WhenClassExists('App\NotAClass'), new WhenMissingService('unrelated')]);
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenMissingService('bar')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceExcluded($container, 'bar', 'because the "App\NotAClass" class is not available');
        $this->assertServiceKept($container, 'foo');
    }

    public function testMissingServiceConditionSeesRuntimeServices()
    {
        $container = new ContainerBuilder();
        $container->set('override', new \stdClass());
        $container->setAlias('override_alias', 'override');
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenMissingService('override')]);
        $container->register('bar', \stdClass::class)
            ->setWhenConditions([new WhenMissingService('override_alias')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceExcluded($container, 'foo', 'because the "override" service is already defined');
        $this->assertServiceExcluded($container, 'bar', 'because the "override_alias" service is already defined');
    }

    public function testRejectedDecoratorDoesNotBreakDecoratedService()
    {
        $container = new ContainerBuilder();
        $container->register('base', \stdClass::class)->setPublic(true);
        $container->register('decorator', \ArrayObject::class)
            ->setDecoratedService('base')
            ->setWhenConditions([new WhenClassExists('App\NotAClass')]);

        $container->compile();

        $this->assertInstanceOf(\stdClass::class, $container->get('base'));
        $this->assertFalse($container->has('decorator'));
    }

    public function testKeptDecoratorStillDecorates()
    {
        $container = new ContainerBuilder();
        $container->register('base', \stdClass::class)->setPublic(true);
        $container->register('decorator', \ArrayObject::class)
            ->setDecoratedService('base')
            ->setWhenConditions([new WhenClassExists(\stdClass::class)]);

        $container->compile();

        $this->assertInstanceOf(\ArrayObject::class, $container->get('base'));
    }

    public function testRejectedTagDecoratorIsInert()
    {
        $container = new ContainerBuilder();
        $container->register('base', \stdClass::class)
            ->setPublic(true)
            ->addTag('app.decorable');
        $container->register('tag_decorator', \ArrayObject::class)
            ->addResourceTag('container.tag_decorator', ['decorates_tag' => 'app.decorable'])
            ->setWhenConditions([new WhenClassExists('App\NotAClass')]);

        $container->compile();

        $this->assertInstanceOf(\stdClass::class, $container->get('base'));
    }

    public function testFqcnServiceIdWithMissingClass()
    {
        $container = new ContainerBuilder();
        $container->register('App\NotInstalled\OptionalService')
            ->setPublic(true)
            ->setWhenConditions([new WhenClassExists('App\NotInstalled\OptionalService')]);

        $container->compile();

        $this->assertFalse($container->has('App\NotInstalled\OptionalService'));
    }

    public function testParameterConditionResolvesExpectedValue()
    {
        $container = new ContainerBuilder();
        $container->setParameter('actual', 'prod');
        $container->setParameter('expected', 'prod');
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenParameter('actual', '%expected%')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceKept($container, 'foo');
    }

    public function testParameterConditionWithArrayValue()
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.locales', ['es', 'en']);
        $container->register('foo', \stdClass::class)
            ->setWhenConditions([new WhenParameter('app.locales', ['es', 'en'])]);
        $container->register('bar', \stdClass::class)
            ->setWhenConditions([new WhenParameter('app.locales', ['es'])]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceKept($container, 'foo');
        $this->assertServiceExcluded($container, 'bar', 'because the "app.locales" parameter does not have the expected value');
    }

    public function testClassConditionsWithPackage()
    {
        $container = new ContainerBuilder();
        $container->register('kept_not_installed_package', \stdClass::class)
            ->setWhenConditions([new WhenClassExists(\stdClass::class, 'acme/not-a-real-package')]);
        $container->register('rejected_missing_class', \stdClass::class)
            ->setWhenConditions([new WhenClassExists('App\NotAClass', 'symfony/yaml')]);
        $container->register('rejected_available_class', \stdClass::class)
            ->setWhenConditions([new WhenClassMissing(\stdClass::class, 'acme/not-a-real-package')]);
        $container->register('kept_missing_class', \stdClass::class)
            ->setWhenConditions([new WhenClassMissing('App\NotAClass', 'acme/not-a-real-package')]);

        (new ResolveConditionalDefinitionsPass())->process($container);

        $this->assertServiceKept($container, 'kept_not_installed_package');
        $this->assertServiceExcluded($container, 'rejected_missing_class', 'because the "App\NotAClass" class from package "symfony/yaml" is not available');
        $this->assertServiceExcluded($container, 'rejected_available_class', 'because the "stdClass" class from package "acme/not-a-real-package" is available');
        $this->assertServiceKept($container, 'kept_missing_class');
    }

    public function testRejectedAsDecoratorAttributeDoesNotBreakDecoratedService()
    {
        $container = new ContainerBuilder();
        $container->register('base', \stdClass::class)->setPublic(true);
        $container->register('decorator', ConditionalAttributeDecorator::class)
            ->setAutowired(true)
            ->setWhenConditions([new WhenClassExists('App\NotAClass')]);

        $container->compile();

        $this->assertInstanceOf(\stdClass::class, $container->get('base'));
        $this->assertFalse($container->has('decorator'));
    }

    public function testKeptAsDecoratorAttributeStillDecorates()
    {
        $container = new ContainerBuilder();
        $container->register('base', \stdClass::class)->setPublic(true);
        $container->register('decorator', ConditionalAttributeDecorator::class)
            ->setAutowired(true)
            ->setWhenConditions([new WhenClassExists(\stdClass::class)]);

        $container->compile();

        $this->assertInstanceOf(ConditionalAttributeDecorator::class, $container->get('base'));
    }

    public function testInlineDefinitionWithWhenConditionsThrows()
    {
        $container = new ContainerBuilder();
        $container->register('consumer', \stdClass::class)
            ->addArgument([
                (new Definition(\stdClass::class))->setWhenConditions([new WhenClassExists('App\NotAClass')]),
            ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid inline definition used in service "consumer": "when" conditions are only supported on definitions registered in the container.');

        (new ResolveConditionalDefinitionsPass())->process($container);
    }

    public function testInlineDefinitionInBindingsWithWhenConditionsThrows()
    {
        $container = new ContainerBuilder();
        $container->register('consumer', \stdClass::class)
            ->setBindings([
                '$inner' => (new Definition(\stdClass::class))->setWhenConditions([new WhenClassExists('App\NotAClass')]),
            ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid inline definition used in service "consumer"');

        (new ResolveConditionalDefinitionsPass())->process($container);
    }

    public function testNestedInlineDefinitionWithWhenConditionsThrows()
    {
        $inner = (new Definition(\stdClass::class))->setWhenConditions([new WhenMissingService('bar')]);
        $outer = (new Definition(\stdClass::class))->addArgument($inner);
        $container = new ContainerBuilder();
        $container->register('consumer', \stdClass::class)->addArgument($outer);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid inline definition used in service "consumer"');

        (new ResolveConditionalDefinitionsPass())->process($container);
    }

    private function assertServiceKept(ContainerBuilder $container, string $id)
    {
        $definition = $container->getDefinition($id);
        $this->assertFalse($definition->isAbstract());
        $this->assertFalse($definition->hasTag('container.excluded'));
        $this->assertSame([], $definition->getWhenConditions());
    }

    private function assertServiceExcluded(ContainerBuilder $container, string $id, string $reason)
    {
        $definition = $container->getDefinition($id);
        $this->assertTrue($definition->isAbstract());
        $this->assertTrue($definition->hasTag('container.excluded'));
        $this->assertSame([['source' => $reason]], $definition->getTag('container.excluded'));
        $this->assertSame([], $definition->getWhenConditions());
    }
}

interface ConditionalHandlerInterface
{
}

class ConditionalHandler implements ConditionalHandlerInterface
{
}

#[AsDecorator('base')]
class ConditionalAttributeDecorator
{
}
