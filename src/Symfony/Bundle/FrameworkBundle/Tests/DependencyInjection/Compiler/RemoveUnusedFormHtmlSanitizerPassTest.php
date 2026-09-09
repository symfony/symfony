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
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
}
