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
use Symfony\Component\Form\AbstractRendererEngine;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Tests\Fixtures\DummyFormRendererEngine;

class FormRendererTest extends TestCase
{
    public function testHumanize()
    {
        $renderer = new FormRenderer(new DummyFormRendererEngine());

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

        $renderer = new FormRenderer(new DummyFormRendererEngine());
        $renderer->searchAndRenderBlock($formView, 'row');
    }

    public function testSearchAndRenderBlockDoesNotKeepTheStateOfAFailedRendering()
    {
        $engine = $this->createFailingEngine();
        $renderer = new FormRenderer($engine);

        $engine->fail = true;
        try {
            $renderer->searchAndRenderBlock($this->createFieldView('alice@example.com'), 'widget');
            $this->fail('The rendering should have failed.');
        } catch (\RuntimeException) {
        }

        $engine->fail = false;
        $this->assertSame('_profile_email_widget: ', $renderer->searchAndRenderBlock($this->createFieldView(''), 'widget'));
    }

    public function testSearchAndRenderBlockDoesNotKeepTheStateWhenLoadingTheBlockFails()
    {
        $engine = $this->createFailingEngine();
        $renderer = new FormRenderer($engine);

        $engine->failToLoad = true;
        try {
            $renderer->searchAndRenderBlock($this->createFieldView('alice@example.com'), 'widget');
            $this->fail('The rendering should have failed.');
        } catch (\RuntimeException) {
        }

        $engine->failToLoad = false;
        $this->assertSame('_profile_email_widget: ', $renderer->searchAndRenderBlock($this->createFieldView(''), 'widget'));
    }

    public function testRenderBlockDoesNotKeepTheStateWhenMergingTheVariablesFails()
    {
        $renderer = new FormRenderer($this->createFailingEngine());
        $view = $this->createFieldView('alice@example.com');
        $view->vars['attr'] = [];

        try {
            $renderer->renderBlock($view, 'form', ['attr' => 'invalid']);
            $this->fail('The rendering should have failed.');
        } catch (\TypeError) {
        }

        $this->assertSame('form: ', $renderer->renderBlock($this->createFieldView(''), 'form'));
    }

    public function testRenderBlockDoesNotKeepTheStateOfAFailedRendering()
    {
        $engine = $this->createFailingEngine();
        $renderer = new FormRenderer($engine);

        $engine->fail = true;
        try {
            $renderer->renderBlock($this->createFieldView('alice@example.com'), 'form');
            $this->fail('The rendering should have failed.');
        } catch (\RuntimeException) {
        }

        $engine->fail = false;
        $this->assertSame('form: ', $renderer->renderBlock($this->createFieldView(''), 'form'));
    }

    private function createFieldView(string $value): FormView
    {
        $view = new FormView();
        $view->vars = [
            'name' => 'email',
            'value' => $value,
            'unique_block_prefix' => '_profile_email',
            'block_prefixes' => ['form', 'text', '_profile_email'],
            'cache_key' => '_profile_email_text',
        ];

        return $view;
    }

    private function createFailingEngine(): AbstractRendererEngine
    {
        return new class extends AbstractRendererEngine {
            public bool $fail = false;
            public bool $failToLoad = false;

            public function renderBlock(FormView $view, mixed $resource, string $blockName, array $variables = []): string
            {
                if ($this->fail) {
                    throw new \RuntimeException('The rendering failed.');
                }

                return $blockName.': '.$variables['value'];
            }

            protected function loadResourceForBlockName(string $cacheKey, FormView $view, string $blockName): bool
            {
                if ($this->failToLoad) {
                    throw new \RuntimeException('The block could not be loaded.');
                }

                $this->resources[$cacheKey][$blockName] = true;

                return true;
            }
        };
    }
}
