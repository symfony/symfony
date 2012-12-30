<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating;

use LogicException;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\StreamingEngineInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;

/**
 * Proxy engine resolves template references to subsequest engine.
 *
 * @author Rafa³ Wrzeszcz <rafal.wrzeszcz@wrzasq.pl>
 */
class ProxyEngine implements EngineInterface
{
    /**
     * DI container.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Template name parser.
     *
     * @var TemplateNameParserInterface
     */
    protected $parser;

    /**
     * Handled templating engine.
     *
     * @var string
     */
    protected $engine;

    /**
     * Target templating engine.
     *
     * @var string
     */
    protected $target;

    /**
     * Constructor.
     *
     * @param ContainerInterface          $container Services container
     * @param TemplateNameParserInterface $parser    Template name parser
     * @param string                      $engine    Defined templating engine
     * @param string                      $target    Destination templating engine
     */
    public function __construct(ContainerInterface $container, TemplateNameParserInterface $parser, $engine, $target)
    {
        $this->container = $container;
        $this->parser = $parser;
        $this->engine = $engine;
        $this->target = $target;
    }

    /**
     * Returns main templating engine.
     *
     * @return EngineInterface Internal templating engine.
     */
    protected function getTemplating()
    {
        return $this->container->get('templating');
    }

    /**
     * {@inheritDoc}
     */
    public function render($name, array $parameters = array())
    {
        $template = $this->parser->parse($name);
        $template->set('engine', $this->target);
        return $this->getTemplating()->render($template, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function stream($name, array $parameters = array())
    {
        $templating = $this->getTemplating();

        if (!$templating instanceof StreamingEngineInterface) {
            throw new LogicException(
                \sprintf(
                    'Template "%s" cannot be streamed as the sub-sequent target engine "%s"'
                    . ' configured to handle it does not implement StreamingEngineInterface.',
                    $name,
                    \get_class($templating)
                )
            );
        }

        $template = $this->parser->parse($name);
        $template->set('engine', $this->target);
        $templating->stream($template, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function exists($name)
    {
        $template = $this->parser->parse($name);
        $template->set('engine', $this->target);
        return $this->getTemplating()->exists($template);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($name)
    {
        $template = $this->parser->parse($name);

        return $template->get('engine') === $this->engine;
    }

    /**
     * Renders a view and returns a Response.
     *
     * @param string   $view       The view name
     * @param array    $parameters An array of parameters to pass to the view
     * @param Response $response   A Response instance
     *
     * @return Response A Response instance
     */
    public function renderResponse($view, array $parameters = array(), Response $response = null)
    {
        $template = $this->parser->parse($view);
        $template->set('engine', $this->target);
        return $this->getTemplating()->renderResponse($template, $parameters, $response);
    }
}
