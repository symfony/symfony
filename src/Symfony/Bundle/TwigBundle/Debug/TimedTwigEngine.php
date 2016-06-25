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

@trigger_error('The '.__NAMESPACE__.'\TimedTwigEngine class is deprecated since version 2.7 and will be removed in 3.0. Use the Twig native profiler instead.', E_USER_DEPRECATED);

use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Config\FileLocatorInterface;

/**
 * Times the time spent to render a template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.7, to be removed in 3.0. Use the Twig native profiler instead.
 */
class TimedTwigEngine extends TwigEngine
{
    protected $stopwatch;

    /**
     * Constructor.
     *
     * @param \Twig_Environment           $environment A \Twig_Environment instance
     * @param TemplateNameParserInterface $parser      A TemplateNameParserInterface instance
     * @param FileLocatorInterface        $locator     A FileLocatorInterface instance
     * @param Stopwatch                   $stopwatch   A Stopwatch instance
     */
    public function __construct(\Twig_Environment $environment, TemplateNameParserInterface $parser, FileLocatorInterface $locator, Stopwatch $stopwatch)
    {
        parent::__construct($environment, $parser, $locator);

        $this->stopwatch = $stopwatch;
    }

    /**
     * {@inheritdoc}
     */
    public function render($name, array $parameters = array())
    {
        $e = $this->stopwatch->start(sprintf('template.twig (%s)', $name), 'template');

        $ret = parent::render($name, $parameters);

        $e->stop();

        return $ret;
    }
}
