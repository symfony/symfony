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

use Symfony\Bridge\Twig\TokenParser\StopwatchTokenParser;
use Symfony\Component\Stopwatch\Stopwatch;
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

    public function __construct(Stopwatch $stopwatch = null, $enabled = true)
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
        return [
            /*
             * {% stopwatch foo %}
             * Some stuff which will be recorded on the timeline
             * {% endstopwatch %}
             */
            new StopwatchTokenParser(null !== $this->stopwatch && $this->enabled),
        ];
    }

    public function getName()
    {
        return 'stopwatch';
    }
}
