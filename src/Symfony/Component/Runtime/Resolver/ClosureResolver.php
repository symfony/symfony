<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Resolver;

use Symfony\Component\Runtime\ResolverInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ClosureResolver implements ResolverInterface
{
    private $closure;
    private $arguments;

    public function __construct(\Closure $closure, \Closure $arguments)
    {
        $this->closure = $closure;
        $this->arguments = $arguments;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(): array
    {
        return [$this->closure, ($this->arguments)()];
    }
}
