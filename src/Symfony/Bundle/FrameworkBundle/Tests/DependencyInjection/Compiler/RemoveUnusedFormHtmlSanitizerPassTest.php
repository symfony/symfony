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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RemoveUnusedFormHtmlSanitizerPass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\DependencyInjection\FormPass;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;

class RemoveUnusedFormHtmlSanitizerPassTest extends TestCase
{
    public function testTypeExtensionIsKeptWhenTheSanitizerIsAvailable()
    {
        $container = new ContainerBuilder();
        $container->register('form.type_extension.form.html_sanitizer', TextType::class);
        $container->register('html_sanitizer', HtmlSanitizer::class);

        (new RemoveUnusedFormHtmlSanitizerPass())->process($container);

        $this->assertTrue($container->hasDefinition('form.type_extension.form.html_sanitizer'));
    }

    public function testTypeExtensionIsRemovedWhenTheSanitizerIsMissing()
    {
        $container = new ContainerBuilder();
        $container->register('form.type_extension.form.html_sanitizer', TextType::class);

        (new RemoveUnusedFormHtmlSanitizerPass())->process($container);

        $this->assertFalse($container->hasDefinition('form.type_extension.form.html_sanitizer'));
    }

    public function testTheTypeExtensionIsRemovedBeforeFormPassCollectsIt()
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));
        (new FrameworkBundle())->build($container);

        $order = [];
        foreach ($container->getCompiler()->getPassConfig()->getBeforeOptimizationPasses() as $i => $pass) {
            $order[$pass::class] = $i;
        }

        $this->assertArrayHasKey(FormPass::class, $order);
        $this->assertArrayHasKey(RemoveUnusedFormHtmlSanitizerPass::class, $order);
        $this->assertLessThan($order[FormPass::class], $order[RemoveUnusedFormHtmlSanitizerPass::class]);
    }
}
