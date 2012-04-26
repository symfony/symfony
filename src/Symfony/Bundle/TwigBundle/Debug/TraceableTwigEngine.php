<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Debug;

use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Bundle\FrameworkBundle\Templating\GlobalVariables;
use Symfony\Component\Templating\Debug\TraceableEngineInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\HttpKernel\Debug\Stopwatch;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/**
 * Times the time spent to render a template and logs rendered templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Pierre Minnieur <pm@pierre-minnieur.de>
 */
class TraceableTwigEngine extends TwigEngine implements TraceableEngineInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Stopwatch
     */
    protected $stopwatch;

    /**
     * @var array
     */
    protected $rendered;

    /**
     * Constructor.
     *
     * @param \Twig_Environment $environment A \Twig_Environment instance
     * @param TemplateNameParserInterface $parser A TemplateNameParserInterface instance
     * @param FileLocatorInterface $locator A FileLocatorInterface instance
     * @param Stopwatch $stopwatch A Stopwatch instance
     * @param LoggerInterface $logger A LoggerInterface instance
     * @param GlobalVariables $globals A GlobalVariables instance
     */
    public function __construct(\Twig_Environment $environment, TemplateNameParserInterface $parser, FileLocatorInterface $locator, Stopwatch $stopwatch, LoggerInterface $logger = null, GlobalVariables $globals = null)
    {
        parent::__construct($environment, $parser, $locator, $globals);

        $this->logger = $logger;
        $this->stopwatch = $stopwatch;
        $this->rendered = array();
    }

    /**
     * {@inheritdoc}
     */
    public function render($name, array $parameters = array())
    {
        $e = $this->stopwatch->start(sprintf('template.twig (%s)', $name), 'template');

        $ret = parent::render($name, $parameters);

        $e->stop();

        $this->rendered[] = array(
            'name' => (string) $name,
            'parameters' => $parameters,
            'reference' => is_string($name) ? $this->parser->parse($name) : $name
        );

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Rendered template "%s".', $name));
        }

        return $ret;
    }

    /**
     * {@inheritDoc}
     */
    public function getRenderedTemplates()
    {
        return $this->rendered;
    }
}
