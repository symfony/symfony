<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Twig;

use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Twig\Environment;

/**
 * Renders Tui widget trees from Twig templates.
 *
 * Templates produce HTML, which the {@see HtmlBuilder} turns into widgets
 * styled by the template CSS:
 *
 *     $renderer = new TuiRenderer($twig, new HtmlBuilder());
 *     $view = $renderer->render('settings.html.twig', ['languages' => $languages]);
 *     $view->querySelector('#language')->on(SelectEvent::class, $onSelect);
 *     $view->mount($tui);
 *
 * Reusable components come from Symfony UX TwigComponent: its <twig:Card>
 * tags render to HTML before the builder sees it. In a full-stack
 * application, TwigComponentBundle wires it. Elsewhere, wire it by hand:
 *
 *     $loader = new FilesystemLoader([__DIR__.'/templates']);
 *     $twig = new Environment($loader);
 *     $twig->setLexer(new ComponentLexer($twig));
 *     $twig->addExtension(new ComponentExtension());
 *     $twig->getRuntime(EscaperRuntime::class)->addSafeClass(ComponentAttributes::class, ['html']);
 *
 *     $dispatcher = new EventDispatcher();
 *     $accessor = PropertyAccess::createPropertyAccessor();
 *     $stack = new ComponentStack();
 *     $factory = new ComponentFactory(new ComponentTemplateFinder($loader, 'components'), new Container(), $accessor, $dispatcher, [], [], $twig);
 *     $renderer = new ComponentRenderer($twig, $dispatcher, $factory, new ComponentProperties($accessor), $stack);
 *     $twig->addRuntimeLoader(new FactoryRuntimeLoader([
 *         ComponentRuntime::class => static fn () => new ComponentRuntime($renderer, new Container(), $stack),
 *     ]));
 *
 * where Container is any PSR-11 container holding the class components.
 *
 * @experimental
 */
final class TuiRenderer
{
    public function __construct(private readonly Environment $twig, private readonly HtmlBuilder $builder)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $name, array $context = []): HtmlView
    {
        return $this->builder->build($this->twig->render($name, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderBlock(string $name, string $block, array $context = []): HtmlView
    {
        return $this->builder->build($this->twig->load($name)->renderBlock($block, $context));
    }
}
