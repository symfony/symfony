<?php

namespace Symfony\Component\Form\Tests\Extension\Templating\Type;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Templating\Type\FormTypeThemeExtension;
use Symfony\Component\Form\FormRendererEngineInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * @author Damian Wróblewski <damianwroblewski75@gmail.com>
 */
class FormTypeThemeExtensionTest extends TypeTestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|FormRendererEngineInterface
     */
    private $formRendererEngine;

    /**
     * @var string
     */
    private $extension;

    protected function setUp()
    {
        $this->formRendererEngine = $this->getMockBuilder(FormRendererEngineInterface::class)->getMock();
        $this->extension = '.html.twig';
        parent::setUp();
    }

    public function testNoTheme()
    {
        $form = $this->factory->create(FormType::class);
        $this->assertNull($form->getConfig()->getOption('theme'));
    }

    public function testWithTheme()
    {
        $setThemeView = null; // to make sure that setTheme receives the right form view
        $theme = '/path/to/theme';
        $this->formRendererEngine->expects($this->once())->method('setTheme')
            ->with($this->isInstanceOf(FormView::class), $theme)
            ->willReturnCallback(function ($view) use (&$setThemeView) {
                $setThemeView = $view;
            });
        $form = $this->factory->create(FormType::class, null, array('theme' => $theme));

        $this->assertEquals($theme, $form->getConfig()->getOption('theme'));

        $view = $form->createView();

        $this->assertEquals($view, $setThemeView);
    }

    public function testWithAutoExtension()
    {
        $setThemeView = null; // to make sure that setTheme receives the right form view
        $theme = '/path/to/theme';
        $this->formRendererEngine->expects($this->once())->method('setTheme')
            ->with($this->isInstanceOf(FormView::class), $theme.$this->extension)
            ->willReturnCallback(function ($view) use (&$setThemeView) {
                $setThemeView = $view;
            });
        $form = $this->factory->create(FormType::class, null, array('theme' => $theme, 'theme_auto_extension' => true));

        $this->assertEquals($theme, $form->getConfig()->getOption('theme'));

        $view = $form->createView();

        $this->assertEquals($view, $setThemeView);
    }

    protected function getTypeExtensions()
    {
        return array(new FormTypeThemeExtension($this->formRendererEngine, $this->extension));
    }
}
