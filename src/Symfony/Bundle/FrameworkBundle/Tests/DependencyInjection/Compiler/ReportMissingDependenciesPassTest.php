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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ReportMissingDependenciesPass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\DependencyInjection\FormPass;
use Symfony\Component\Serializer\DependencyInjection\SerializerPass;
use Symfony\Component\Serializer\SerializerBundle;

class ReportMissingDependenciesPassTest extends TestCase
{
    /**
     * @param array<string, string> $dependents  service id => the service it needs
     */
    #[DataProvider('provideDependents')]
    public function testTheContainerDropsWhatThisBundleCannotWire(string $file, array $dependents)
    {
        $kept = $this->loadConfig($file);
        foreach (array_values(array_unique($dependents)) as $needed) {
            $kept->register($needed);
        }
        new RemoveMissingDependenciesPass()->process($kept);

        $dropped = $this->loadConfig($file);
        new RemoveMissingDependenciesPass()->process($dropped);

        foreach ($dependents as $id => $needed) {
            $this->assertTrue($kept->has($id), $id);
            $this->assertFalse($dropped->has($id), \sprintf('"%s" is dropped without "%s".', $id, $needed));
        }
    }

    public static function provideDependents(): iterable
    {
        yield 'console' => ['console.php', [
            'console.command.router_debug' => 'router',
            'console.command.serializer_debug' => 'serializer',
            'console.command.validator_debug' => 'validator',
            '.console.validate_question_input_listener' => 'validator',
        ]];

        yield 'form' => ['form.php', [
            'form.type_extension.form.html_sanitizer' => 'html_sanitizer',
        ]];
    }

    private function loadConfig(string $file): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));
        new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 3).'/Resources/config'))->load($file);

        return $container;
    }

    public function testTheServicesThatCannotBeDroppedReportWhatIsMissing()
    {
        $container = $this->createContainer();

        new ReportMissingDependenciesPass()->process($container);

        $transport = $container->getDefinition('webhook.transport');
        $this->assertTrue($transport->hasTag('container.error'));
        $this->assertStringContainsString('You cannot use the "webhook transport" service', $transport->getErrors()[0]);

        $resolver = $container->getDefinition('argument_resolver.request_payload');
        $this->assertTrue($resolver->hasTag('container.error'));
        $this->assertFalse($resolver->hasTag('kernel.event_subscriber'));
        $this->assertStringContainsString('You can neither use "#[MapRequestPayload]" nor "#[MapQueryString]"', $resolver->getErrors()[0]);
    }

    public function testTheContainerDropsTheUnwirableServicesBeforeAnythingCollectsThem()
    {
        // the bundles register their passes at the default priority, and SerializerBundle builds
        // before this one, so only a pass the container itself registers can beat both collectors
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
    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('webhook.transport');
        $container->register('argument_resolver.request_payload')->addTag('kernel.event_subscriber');

        return $container;
    }
}
