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
use Twig\TokenParser\TokenParserInterface;

/**
 * Twig extension for the stopwatch helper.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
final class StopwatchExtension extends AbstractExtension
{
    public function __construct(
        private ?Stopwatch $stopwatch = null,
        private bool $enabled = true,
    ) {
    }

    public function getStopwatch(): Stopwatch
    {
        return $this->stopwatch;
    }

    /**
     * @return TokenParserInterface[]
     */
    public function getTokenParsers(): array
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
}
