<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\ResolvedApp;

use Symfony\Component\Runtime\ResolvedAppInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ScalarResolved implements ResolvedAppInterface
{
    private $closure;

    public function __construct(\Closure $closure)
    {
        $this->closure = $closure;
    }

    public function __invoke(): object
    {
        return $this->closure;
    }
}
