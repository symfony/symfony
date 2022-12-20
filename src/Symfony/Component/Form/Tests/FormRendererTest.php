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
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Tests\Fixtures\DummyFormRendererEngine;

class FormRendererTest extends TestCase
{
    public function testHumanize()
    {
        $renderer = new FormRenderer(new DummyFormRendererEngine());

        self::assertEquals('Is active', $renderer->humanize('is_active'));
        self::assertEquals('Is active', $renderer->humanize('isActive'));
    }

    public function testRenderARenderedField()
    {
        self::expectException(BadMethodCallException::class);
        self::expectExceptionMessage('Field "foo" has already been rendered, save the result of previous render call to a variable and output that instead.');

        $formView = new FormView();
        $formView->vars['name'] = 'foo';
        $formView->setRendered();

        $renderer = new FormRenderer(new DummyFormRendererEngine());
        $renderer->searchAndRenderBlock($formView, 'row');
    }
}
