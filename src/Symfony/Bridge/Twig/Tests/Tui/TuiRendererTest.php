<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Tui;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Tui\TuiRenderer;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Widget\DropdownWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TuiRendererTest extends TestCase
{
    public function testRenderBuildsTheWidgetTreeOfTheTemplate()
    {
        $view = $this->createRenderer([
            'form.html.twig' => <<<'TWIG'
                <style>
                    .field { font-weight: bold }
                </style>
                <main id="form">
                    <label for="name">Name</label>
                    <input id="name" class="field" value="{{ name }}">
                    <select id="language" aria-label="Language">
                        {% for language in languages %}
                            <option value="{{ language }}">{{ language|upper }}</option>
                        {% endfor %}
                    </select>
                </main>
                TWIG,
        ])->render('form.html.twig', ['name' => 'Fabien', 'languages' => ['php', 'rust']]);

        $input = $view->getById('name');
        $this->assertInstanceOf(InputWidget::class, $input);
        $this->assertSame('Fabien', $input->getValue());
        $this->assertSame('Name', $input->getLabel());
        $this->assertSame(['field'], $input->getStyleClasses());

        $select = $view->getById('language');
        $this->assertInstanceOf(DropdownWidget::class, $select);
        $this->assertSame('Language', $select->getLabel());

        $view->getStyleSheet()->prepare($view->getRoot(), new MediaContext());
        $styles = $view->getStyleSheet()->getStyles($input);
        $this->assertCount(1, $styles);
        $this->assertTrue($styles[0]->getBold());
    }

    public function testRenderEscapesTerminalSequencesOfTheData()
    {
        $view = $this->createRenderer(['t.html.twig' => '<p id="text">{{ text }}</p>'])
            ->render('t.html.twig', ['text' => "<b>evil</b>\e]0;title\x07"]);

        $text = $view->getById('text');
        $this->assertInstanceOf(TextWidget::class, $text);
        $this->assertSame('<b>evil</b>]0;title', $text->getText());
    }

    public function testRenderBlock()
    {
        $view = $this->createRenderer([
            'page.html.twig' => '{% block header %}<h1 id="title">{{ title }}</h1>{% endblock %}<p>body</p>',
        ])->renderBlock('page.html.twig', 'header', ['title' => 'Hello']);

        $this->assertSame('Hello', $view->getById('title')->getText());
        $this->assertSame([], $view->querySelectorAll('p'));
    }

    /**
     * @param array<string, string> $templates
     */
    private function createRenderer(array $templates): TuiRenderer
    {
        return new TuiRenderer(new Environment(new ArrayLoader($templates)), new HtmlBuilder());
    }
}
