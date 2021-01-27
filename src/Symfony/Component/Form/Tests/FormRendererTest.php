<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormRendererEngineInterface;
use Symfony\Component\Form\FormView;

class FormRendererTest extends TestCase
{
    public function testHumanize()
    {
        $renderer = $this->getMockBuilder(FormRenderer::class)
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->assertEquals('Is active', $renderer->humanize('is_active'));
        $this->assertEquals('Is active', $renderer->humanize('isActive'));
    }

    public function testRenderARenderedField()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Field "foo" has already been rendered, save the result of previous render call to a variable and output that instead.');

        $formView = new FormView();
        $formView->vars['name'] = 'foo';
        $formView->setRendered();

        $engine = $this->createMock(FormRendererEngineInterface::class);
        $renderer = new FormRenderer($engine);
        $renderer->searchAndRenderBlock($formView, 'row');
    }
}
