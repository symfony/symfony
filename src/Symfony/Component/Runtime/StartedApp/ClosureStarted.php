<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\StartedApp;

use Symfony\Component\Runtime\StartedAppInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ClosureStarted implements StartedAppInterface
{
    private $app;

    public function __construct(\Closure $app)
    {
        $this->app = $app;
    }

    public function __invoke(): int
    {
        return ($this->app)() ?? 0;
    }
}
