<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Twig\DocumentRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class DocumentRendererTest extends TestCase
{
    public function testTemplateCreatesNativeInteractiveDocumentSession()
    {
        $renderer = new DocumentRenderer(new Environment(new ArrayLoader(['form.html.twig' => '<style>.field{font-weight:bold}</style><form><input class="field" name="name" value="{{ name }}"></form>'])));
        $session = $renderer->render('form.html.twig', new RenderingContext(400, 200), ['name' => 'Ada']);
        $input = $session->getDocument()->querySelector('input');
        self::assertSame('Ada', $session->getValue($input));
        self::assertSame('700', $session->render()->getComputedStyle($input)['font-weight']);
        $session->focus($input);
        $session->key(new \Symfony\Component\HtmlRenderer\Event\KeyboardEvent('End'));
        $session->key(new \Symfony\Component\HtmlRenderer\Event\KeyboardEvent('Unidentified', '!'));
        self::assertSame('Ada!', $session->getValue($input));
        self::assertSame(['name' => 'Ada!'], $session->submit($session->getDocument()->querySelector('form'))->values);
    }

    public function testBlockRenderingKeepsTwigEscapingAndExcludesOtherBlocks()
    {
        $renderer = new DocumentRenderer(new Environment(new ArrayLoader(['page.html.twig' => '{% block header %}<h1>{{ title }}</h1>{% endblock %}<p>body</p>'])));
        $session = $renderer->renderBlock('page.html.twig', 'header', new RenderingContext(400, 200), ['title' => '<b>Hello</b>']);
        self::assertSame('<b>Hello</b>', $session->getDocument()->querySelector('h1')->textContent);
        self::assertNull($session->getDocument()->querySelector('b'));
        self::assertNull($session->getDocument()->querySelector('p'));
    }
}
