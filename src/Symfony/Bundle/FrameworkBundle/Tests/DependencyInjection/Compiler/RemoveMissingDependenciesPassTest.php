<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RemoveMissingDependenciesPass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\DependencyInjection\FormPass;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\Serializer\DependencyInjection\SerializerPass;
use Symfony\Component\Serializer\SerializerBundle;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testEverythingIsKeptWhenEveryBundleRegisteredItsService()
    {
        $container = $this->createContainer();
        foreach (['html_sanitizer' => HtmlSanitizer::class, 'http_client' => \stdClass::class, 'router' => \stdClass::class, 'serializer' => \stdClass::class, 'validator' => \stdClass::class] as $id => $class) {
            $container->register($id, $class);
        }

        new RemoveMissingDependenciesPass()->process($container);

        foreach (array_keys($this->dependents()) as $id) {
            $this->assertTrue($container->has($id), $id);
        }
        $this->assertSame([], $container->getDefinition('webhook.transport')->getErrors());
        $this->assertSame([], $container->getDefinition('argument_resolver.request_payload')->getErrors());
    }

    public function testEachServiceGoesWithTheBundleThatWouldProvideWhatItNeeds()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        foreach ($this->dependents() as $id => $missing) {
            $this->assertFalse($container->has($id), \sprintf('"%s" is dropped without "%s".', $id, $missing));
        }
    }

    public function testTheServicesThatCannotBeDroppedReportWhatIsMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $transport = $container->getDefinition('webhook.transport');
        $this->assertTrue($transport->hasTag('container.error'));
        $this->assertStringContainsString('You cannot use the "webhook transport" service', $transport->getErrors()[0]);

        $resolver = $container->getDefinition('argument_resolver.request_payload');
        $this->assertTrue($resolver->hasTag('container.error'));
        $this->assertFalse($resolver->hasTag('kernel.event_subscriber'));
        $this->assertStringContainsString('You can neither use "#[MapRequestPayload]" nor "#[MapQueryString]"', $resolver->getErrors()[0]);
    }

    public function testItRunsBeforeThePassesThatCollectWhatItRemoves()
    {
        // SerializerBundle is a required bundle, so it registers SerializerPass while building
        // before this one; the priority, not the registration order, is what puts this pass first
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));
        new SerializerBundle()->build($container);
        new FrameworkBundle()->build($container);

        $order = [];
        foreach ($container->getCompiler()->getPassConfig()->getBeforeOptimizationPasses() as $i => $pass) {
            $order[$pass::class] = $i;
        }

        $this->assertArrayHasKey(RemoveMissingDependenciesPass::class, $order);
        foreach ([FormPass::class, SerializerPass::class] as $collector) {
            $this->assertArrayHasKey($collector, $order);
            $this->assertLessThan($order[$collector], $order[RemoveMissingDependenciesPass::class], $collector);
        }
    }

    /**
     * @return array<string, string> service id => the service it needs
     */
    private function dependents(): array
    {
        return [
            'form.type_extension.form.html_sanitizer' => 'html_sanitizer',
            'console.command.router_debug' => 'router',
            'console.command.serializer_debug' => 'serializer',
            'console.command.validator_debug' => 'validator',
        ];
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('form.type_extension.form.html_sanitizer', TextType::class);
        $container->register('console.command.router_debug');
        $container->register('console.command.serializer_debug');
        $container->register('console.command.validator_debug');
        $container->register('webhook.transport');
        $container->register('argument_resolver.request_payload')->addTag('kernel.event_subscriber');

        return $container;
    }
}
