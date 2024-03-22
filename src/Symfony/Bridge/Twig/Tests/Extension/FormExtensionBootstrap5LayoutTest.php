<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Class providing test cases for the Bootstrap 5 Twig form theme.
 *
 * @author Romain Monteil <monteil.romain@gmail.com>
 */
class FormExtensionBootstrap5LayoutTest extends AbstractBootstrap5LayoutTestCase
{
    public function testStartTagHasNoActionAttributeWhenActionIsEmpty()
    {
        $form = $this->factory->create(FormType::class, null, [
            'method' => 'get',
            'action' => '',
        ]);

        $html = $this->renderStart($form->createView());

        self::assertSame('<form name="form" method="get">', $html);
    }

    public function testStartTagHasActionAttributeWhenActionIsZero()
    {
        $form = $this->factory->create(FormType::class, null, [
            'method' => 'get',
            'action' => '0',
        ]);

        $html = $this->renderStart($form->createView());

        self::assertSame('<form name="form" method="get" action="0">', $html);
    }

    public function testMoneyWidgetInIso()
    {
        $environment = new Environment(new FilesystemLoader([
            __DIR__.'/../../Resources/views/Form',
            __DIR__.'/Fixtures/templates/form',
        ]), ['strict_variables' => true]);
        $environment->addExtension(new TranslationExtension(new StubTranslator()));
        $environment->addExtension(new FormExtension());
        $environment->setCharset('ISO-8859-1');

        $rendererEngine = new TwigRendererEngine([
            'bootstrap_5_layout.html.twig',
            'custom_widgets.html.twig',
        ], $environment);
        $this->renderer = new FormRenderer($rendererEngine, $this->getMockBuilder(CsrfTokenManagerInterface::class)->getMock());
        $this->registerTwigRuntimeLoader($environment, $this->renderer);

        $view = $this->factory
            ->createNamed('name', MoneyType::class)
            ->createView();

        self::assertSame(<<<'HTML'
<div class="input-group "><span class="input-group-text">&euro; </span><input type="text" id="name" name="name" required="required" class="form-control" /></div>
HTML
            , trim($this->renderWidget($view)));
    }

    protected function getTemplatePaths(): array
    {
        return [
            __DIR__.'/../../Resources/views/Form',
            __DIR__.'/Fixtures/templates/form',
        ];
    }

    protected function getTwigExtensions(): array
    {
        return [
            new TranslationExtension(new StubTranslator()),
            new FormExtension(),
        ];
    }

    protected function getThemes(): array
    {
        return [
            'bootstrap_5_layout.html.twig',
            'custom_widgets.html.twig',
        ];
    }
}
