<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command\Resolver;

/**
 * @author Nikita Konstantinov
 *
 * @api
 */
final class ClosureCommandResolver implements CommandResolverInterface
{
    /**
     * @var \Closure
     */
    private $closure;

    /**
     * @param \Closure $closure
     */
    public function __construct(\Closure $closure)
    {
        $this->closure = $closure;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve($commandName)
    {
        $command = call_user_func($this->closure, $commandName);

        if (null === $command) {
            throw new CommandResolutionException(sprintf('Command "%s" could not be resolved', $commandName));
        }

        return $command;
    }
}
