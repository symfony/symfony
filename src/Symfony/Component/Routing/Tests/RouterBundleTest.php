<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\Routing\DependencyInjection\LocalizedRoutesPass;
use Symfony\Component\Routing\RouterBundle;

class RouterBundleTest extends TestCase
{
    public function testTheRouterIsOffUntilItIsConfigured()
    {
        // an auto-registered bundle is loaded with an empty configuration, which must not enable it
        $this->assertFalse($this->load([])->hasDefinition('router.default'));
        $this->assertFalse($this->load(['enabled' => false])->hasDefinition('router.default'));
    }

    public function testRouter()
    {
        $container = $this->load(['resource' => '%kernel.project_dir%/config/routing.xml', 'type' => 'xml', 'utf8' => true], ['fr', 'en']);

        $this->assertTrue($container->has('router'), '->registerRouterConfiguration() loads routing.xml');
        $arguments = $container->findDefinition('router')->getArguments();
        $this->assertEquals($container->getParameter('kernel.project_dir').'/config/routing.xml', $container->getParameter('router.resource'), '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('%router.resource%', $arguments[1], '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('xml', $arguments[2]['resource_type'], '->registerRouterConfiguration() sets routing resource type');

        $this->assertSame(['_locale' => 'fr|en'], $container->getDefinition('routing.loader')->getArgument(2));
    }

    public function testRouterRequestContextInlinesHostAndScheme()
    {
        $container = $this->load(['resource' => '%kernel.project_dir%/config/routing.xml', 'type' => 'xml', 'utf8' => true], ['fr', 'en']);

        // The host and scheme are inlined as plain values instead of being read through
        // ParameterBag::all() at runtime, which would eagerly resolve every env var and
        // fail during cache warmup when one of them is missing.
        $requestContext = $container->getDefinition('router.request_context');
        $this->assertSame('localhost', $requestContext->getArgument(1));
        $this->assertSame('http', $requestContext->getArgument(2));
    }

    public function testRouterRequestContextUsesHostAndSchemeParameters()
    {
        $container = $this->load(['resource' => '%kernel.project_dir%/config/routing.xml'], [], [
            'router.request_context.host' => 'example.com',
            'router.request_context.scheme' => 'https',
        ]);

        $requestContext = $container->getDefinition('router.request_context');
        $this->assertSame('example.com', $requestContext->getArgument(1));
        $this->assertSame('https', $requestContext->getArgument(2));
    }

    public function testRouterEnabledLocalesWithEnvPlaceholders()
    {
        $container = $this->load(['resource' => '%kernel.project_dir%/config/routing.xml'], ['%env(ROUTER_ENABLED_LOCALE)%', 'fr']);
        $requirements = $container->getDefinition('routing.loader')->getArgument(2);

        $this->assertIsArray($requirements);
        $this->assertArrayHasKey('_locale', $requirements);

        $requirementDefinition = $requirements['_locale'];
        $this->assertInstanceOf(Definition::class, $requirementDefinition);
        $this->assertSame('implode', $requirementDefinition->getFactory());

        $this->assertSame('|', $requirementDefinition->getArgument(0));

        $arrayMap = $requirementDefinition->getArgument(1);
        $this->assertInstanceOf(Definition::class, $arrayMap);
        $this->assertSame('array_map', $arrayMap->getFactory());
        $this->assertSame('preg_quote', $arrayMap->getArgument(0));
    }

    public function testRouterRequiresResourceOption()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "resource" under "router" must be configured.');

        $this->load(['enabled' => true]);
    }

    /**
     * @param list<string>         $enabledLocales
     * @param array<string, mixed> $parameters
     */
    private function load(array $config, array $enabledLocales = [], array $parameters = []): ContainerBuilder
    {
        $bag = new EnvPlaceholderParameterBag($parameters + [
            'kernel.debug' => false,
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.project_dir' => '/app',
        ]);
        // %env()% only becomes a placeholder once the bag has resolved it, which is what
        // LocalizedRoutesPass keys on to decide between a literal and a computed requirement
        $bag->set('kernel.enabled_locales', array_map($bag->resolveValue(...), $enabledLocales));

        $container = new ContainerBuilder($bag);
        $container->registerExtension(new RouterBundle()->getContainerExtension());
        $container->loadFromExtension('router', $config);
        new MergeExtensionConfigurationPass()->process($container);
        new LocalizedRoutesPass()->process($container);

        return $container;
    }
}
