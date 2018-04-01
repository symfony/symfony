<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Templating;

use Psr\Container\ContainerInterface;
use Symphony\Component\Templating\TemplateNameParserInterface;
use Symphony\Component\Stopwatch\Stopwatch;
use Symphony\Component\Templating\Loader\LoaderInterface;

/**
 * Times the time spent to render a template.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class TimedPhpEngine extends PhpEngine
{
    protected $stopwatch;

    public function __construct(TemplateNameParserInterface $parser, ContainerInterface $container, LoaderInterface $loader, Stopwatch $stopwatch, GlobalVariables $globals = null)
    {
        parent::__construct($parser, $container, $loader, $globals);

        $this->stopwatch = $stopwatch;
    }

    /**
     * {@inheritdoc}
     */
    public function render($name, array $parameters = array())
    {
        $e = $this->stopwatch->start(sprintf('template.php (%s)', $name), 'template');

        $ret = parent::render($name, $parameters);

        $e->stop();

        return $ret;
    }
}
