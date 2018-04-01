<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Extension;

use Symphony\Component\Stopwatch\Stopwatch;
use Symphony\Bridge\Twig\TokenParser\StopwatchTokenParser;
use Twig\Extension\AbstractExtension;

/**
 * Twig extension for the stopwatch helper.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class StopwatchExtension extends AbstractExtension
{
    private $stopwatch;
    private $enabled;

    public function __construct(Stopwatch $stopwatch = null, bool $enabled = true)
    {
        $this->stopwatch = $stopwatch;
        $this->enabled = $enabled;
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
            new StopwatchTokenParser(null !== $this->stopwatch && $this->enabled),
        );
    }

    public function getName()
    {
        return 'stopwatch';
    }
}
