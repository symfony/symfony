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
    public function testViewsSharingACacheKeyCanHaveDifferentBlockHierarchies()
    {
        $entry = ['form', 'collection_entry', 'task', '_task'];
        $prototype = ['form', 'task', '_task'];

        $this->assertSame(['[task_widget]', '[task_widget]'], $this->renderWidgets(['task_widget' => true], $entry, $prototype));
        $this->assertSame(['[task_widget]', '[task_widget]'], $this->renderWidgets(['task_widget' => true], $prototype, $entry));
    }

    public function testViewsSharingACacheKeyFallBackAlongTheirOwnBlockHierarchy()
    {
        $entry = ['form', 'collection_entry', 'task', '_task'];
        $prototype = ['form', 'task', '_task'];
        $blocks = ['form_widget' => true, 'collection_entry_widget' => true];

        $this->assertSame(['[collection_entry_widget]', '[form_widget]'], $this->renderWidgets($blocks, $entry, $prototype));
        $this->assertSame(['[form_widget]', '[collection_entry_widget]'], $this->renderWidgets($blocks, $prototype, $entry));
    }

    public function testHumanize()
    {
        $renderer = new FormRenderer(new DummyFormRendererEngine());

        $this->assertEquals('Is active', $renderer->humanize('is_active'));
        $this->assertEquals('Is active', $renderer->humanize('isActive'));
    }

    public function testSearchAndRenderBlockResumesEachRecursiveCallFromTheLevelOfTheCallingBlock()
    {
        $engine = new class extends AbstractRendererEngine {
            public FormRenderer $renderer;

            public function renderBlock(FormView $view, mixed $resource, string $blockName, array $variables = []): string
            {
                if (str_starts_with($blockName, '_')) {
                    return $this->renderer->searchAndRenderBlock($view, 'label').$this->renderer->searchAndRenderBlock($view, 'label');
                }

                return '['.$blockName.']';
            }

            protected function loadResourceForBlockName(string $cacheKey, FormView $view, string $blockName): bool
            {
                $this->resources[$cacheKey][$blockName] = true;

                return true;
            }
        };
        $engine->renderer = $renderer = new FormRenderer($engine);

        $view = new FormView();
        $view->vars = ['name' => 'email', 'unique_block_prefix' => '_profile_email', 'block_prefixes' => ['form', 'text', '_profile_email'], 'cache_key' => '_profile_email_text'];
        $this->assertSame('[text_label][text_label]', $renderer->searchAndRenderBlock($view, 'label'));

        $view = new FormView();
        $view->vars = ['name' => 'name', 'unique_block_prefix' => '_profile_name', 'block_prefixes' => ['form', '_profile_name'], 'cache_key' => '_profile_name_form'];
        $this->assertSame('[form_label][form_label]', $renderer->searchAndRenderBlock($view, 'label'));
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

    private function renderWidgets(array $blocks, array ...$blockPrefixes): array
    {
        $engine = new class($blocks) extends AbstractRendererEngine {
            public function __construct(private array $blocks)
            {
                parent::__construct();
            }

            public function renderBlock(FormView $view, mixed $resource, string $blockName, array $variables = []): string
            {
                return '['.$blockName.']';
            }

            protected function loadResourceForBlockName(string $cacheKey, FormView $view, string $blockName): bool
            {
                return $this->resources[$cacheKey][$blockName] = isset($this->blocks[$blockName]);
            }
        };
        $renderer = new FormRenderer($engine);

        $html = [];
        foreach ($blockPrefixes as $prefixes) {
            $view = new FormView();
            $view->vars = ['name' => 'task', 'unique_block_prefix' => '_task', 'cache_key' => '_task_task', 'block_prefixes' => $prefixes];
            $html[] = $renderer->searchAndRenderBlock($view, 'widget');
        }

        return $html;
    }
}
