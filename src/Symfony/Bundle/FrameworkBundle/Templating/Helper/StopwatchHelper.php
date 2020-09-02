<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

@trigger_error('The '.StopwatchHelper::class.' class is deprecated since version 4.3 and will be removed in 5.0; use Twig instead.', \E_USER_DEPRECATED);

use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Templating\Helper\Helper;

/**
 * StopwatchHelper provides methods time your PHP templates.
 *
 * @author Wouter J <wouter@wouterj.nl>
 *
 * @deprecated since version 4.3, to be removed in 5.0; use Twig instead.
 */
class StopwatchHelper extends Helper
{
    private $stopwatch;

    public function __construct(Stopwatch $stopwatch = null)
    {
        $this->stopwatch = $stopwatch;
    }

    public function getName()
    {
        return 'stopwatch';
    }

    public function __call($method, $arguments = [])
    {
        if (null === $this->stopwatch) {
            return null;
        }

        if (method_exists($this->stopwatch, $method)) {
            return $this->stopwatch->{$method}(...$arguments);
        }

        throw new \BadMethodCallException(sprintf('Method "%s" of Stopwatch does not exist.', $method));
    }
}
