<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Bridge\Twig\TokenParser\StopwatchTokenParser;

/**
 * Twig extension for the stopwatch helper.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class StopwatchExtension extends \Twig_Extension
{
    private $stopwatch;

    public function __construct(Stopwatch $stopwatch = null)
    {
        $this->stopwatch = $stopwatch;
    }

    public function getStopwatch()
    {
        return $this->stopwatch;
    }

    public function getTokenParsers()
    {
        return array(
            /*
             * {% stopwatch foo %}
             * Some stuff which will be recorded on the timeline
             * {% endstopwatch %}
             */
            new StopwatchTokenParser($this->stopwatch !== null),
        );
    }

    public function getName()
    {
        return 'stopwatch';
    }
}
