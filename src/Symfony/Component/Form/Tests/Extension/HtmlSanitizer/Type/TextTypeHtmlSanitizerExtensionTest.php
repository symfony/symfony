<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\HtmlSanitizer\Type;

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\HtmlSanitizer\HtmlSanitizerExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class TextTypeHtmlSanitizerExtensionTest extends TypeTestCase
{
    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();

        parent::setUp();
    }

    protected function getExtensions(): array
    {
        $fooSanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $fooSanitizer->expects($this->once())
            ->method('sanitize')
            ->with('foobar')
            ->willReturn('foo');

        $barSanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $barSanitizer->expects($this->once())
            ->method('sanitize')
            ->with('foobar')
            ->willReturn('bar');

        return array_merge(parent::getExtensions(), [
            new HtmlSanitizerExtension(new ServiceLocator([
                'foo' => static fn () => $fooSanitizer,
                'bar' => static fn () => $barSanitizer,
            ]), 'foo'),
        ]);
    }

    public function testSanitizer()
    {
        $form = $this->factory->createBuilder(FormType::class, ['data' => null])
            ->add('data', TextType::class, ['sanitize_html' => true])
            ->getForm()
        ;
        $form->submit(['data' => 'foobar']);

        $this->assertSame(['data' => 'foo'], $form->getData());

        $form = $this->factory->createBuilder(FormType::class, ['data' => null])
            ->add('data', TextType::class, ['sanitize_html' => true, 'sanitizer' => 'bar'])
            ->getForm()
        ;
        $form->submit(['data' => 'foobar']);

        $this->assertSame(['data' => 'bar'], $form->getData());
    }
}
