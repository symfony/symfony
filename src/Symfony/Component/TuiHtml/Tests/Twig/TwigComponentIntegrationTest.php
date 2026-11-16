<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Style\Density;
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Twig\TuiExtension;
use Symfony\Component\TuiHtml\Twig\TuiRenderer;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\TuiHtml\Widget\TextWidget;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\ComponentFactory;
use Symfony\UX\TwigComponent\ComponentProperties;
use Symfony\UX\TwigComponent\ComponentRenderer;
use Symfony\UX\TwigComponent\ComponentStack;
use Symfony\UX\TwigComponent\ComponentTemplateFinder;
use Symfony\UX\TwigComponent\Twig\ComponentExtension;
use Symfony\UX\TwigComponent\Twig\ComponentLexer;
use Symfony\UX\TwigComponent\Twig\ComponentRuntime;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\LoaderInterface;
use Twig\Runtime\EscaperRuntime;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * Renders Symfony UX TwigComponent components into widgets, with
 * TwigComponent wired standalone, as an application without a kernel does.
 */
class TwigComponentIntegrationTest extends TestCase
{
    private const CARD = <<<'TWIG'
        {% props title, tone = 'neutral' %}
        <section{{ attributes.defaults({class: 'card card--' ~ tone}) }}>
            <h2 class="card__title">{{ title }}</h2>
            {% block content %}{% endblock %}
            <footer class="card__footer">{% block footer %}<p>Default footer</p>{% endblock %}</footer>
            <style>
                .card { border: 1px solid }
                .card--info .card__title { font-weight: bold }
                .card:condensed { border: none }
                {% if attributes is condensed %}.card__footer { display: none }{% endif %}
            </style>
        </section>
        TWIG;
    private const PAGE = <<<'TWIG'
        <twig:Card title="Files" tone="info" class="wide" id="files">
            <p id="body">Body</p>
            <twig:block name="footer"><p id="foot">Custom footer</p></twig:block>
        </twig:Card>
        TWIG;

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(ComponentFactory::class)) {
            self::markTestSkipped('symfony/ux-twig-component is not installed.');
        }
    }

    public function testAnonymousComponentWithPropsBlocksAndAttributes()
    {
        $view = $this->render(new ArrayLoader(['page.html.twig' => self::PAGE, 'components/Card.html.twig' => self::CARD]));
        $card = $view->getById('files');
        $this->assertInstanceOf(ContainerWidget::class, $card);
        $this->assertEqualsCanonicalizing(['card', 'card--info', 'wide'], $card->getStyleClasses());
        $title = $view->querySelector('#files > h2');
        $this->assertInstanceOf(TextWidget::class, $title);
        $this->assertSame('Files', $title->getText());
        $this->assertSame('Body', $view->getById('body')->getText());
        $this->assertSame('Custom footer', $view->getById('foot')->getText());
        $this->assertSame([], $view->querySelectorAll('footer p:not(#foot)'), 'The footer block is overridden.');
        $styleSheet = $view->getStyleSheet();
        $styleSheet->prepare($view->getRoot(), new MediaContext());
        $this->assertTrue($styleSheet->getStyles($title)[0]->getBold());
        $this->assertSame(1, $styleSheet->getStyles($card)[0]->getBorder()?->top);
    }

    public function testCondensedComponent()
    {
        $view = $this->render(new ArrayLoader(['page.html.twig' => '<twig:Card title="Files" id="files" data-density="condensed">Body</twig:Card>', 'components/Card.html.twig' => self::CARD]));
        $styleSheet = $view->getStyleSheet();
        $styleSheet->prepare($view->getRoot(), new MediaContext());
        $card = $view->getById('files');
        $this->assertSame(0, $styleSheet->getStyles($card)[0]->getBorder()?->top, 'The :condensed rule applies.');
        $footer = $view->querySelector('.card__footer');
        $this->assertTrue($styleSheet->getStyles($footer)[0]->getHidden(), 'The "condensed" test changes the markup.');
    }

    public function testComponentTemplateCanBeOverridden()
    {
        $view = $this->render(new ChainLoader([new ArrayLoader(['components/Card.html.twig' => '{% props title %}<aside{{ attributes.defaults({class: "custom-card"}) }}><p>{{ title }}</p></aside>']), new ArrayLoader(['page.html.twig' => self::PAGE, 'components/Card.html.twig' => self::CARD])]));
        $card = $view->getById('files');
        $this->assertEqualsCanonicalizing(['custom-card', 'wide'], $card->getStyleClasses());
        $this->assertSame('aside', $view->getStyleSheet()->getDescription($card)[0]);
        $this->assertSame('Files', $view->querySelector('aside > p')->getText());
    }

    private function render(LoaderInterface $loader): HtmlView
    {
        $twig = new Environment($loader);
        $twig->setLexer(new ComponentLexer($twig));
        $twig->addExtension(new ComponentExtension());
        $twig->addExtension(new TuiExtension(Density::Regular));
        $twig->getRuntime(EscaperRuntime::class)->addSafeClass(ComponentAttributes::class, ['html']);
        $dispatcher = new EventDispatcher();
        $accessor = PropertyAccess::createPropertyAccessor();
        $stack = new ComponentStack();
        $factory = new ComponentFactory(new ComponentTemplateFinder($loader, 'components'), new ServiceLocator([]), $accessor, $dispatcher, [], [], $twig);
        $renderer = new ComponentRenderer($twig, $dispatcher, $factory, new ComponentProperties($accessor), $stack);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([ComponentRuntime::class => static fn () => new ComponentRuntime($renderer, new ServiceLocator([]), $stack)]));

        return (new TuiRenderer($twig, new HtmlBuilder()))->render('page.html.twig');
    }
}
