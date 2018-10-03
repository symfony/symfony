<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Exception\MoreThanOneHandlerForMessageException;
use Symfony\Component\Messenger\Handler\ChainHandler;
use Symfony\Component\Messenger\Handler\Locator\HandlerLocatorInterface;

/**
 * @author Kamil Kokot <kamil@kokot.me>
 */
class AllowSingleHandlerMiddleware implements MiddlewareInterface
{
    private $messageHandlerResolver;

    public function __construct(HandlerLocatorInterface $messageHandlerResolver)
    {
        $this->messageHandlerResolver = $messageHandlerResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function handle($message, callable $next)
    {
        $handler = $this->messageHandlerResolver->resolve($message);

        if ($handler instanceof ChainHandler) {
            throw new MoreThanOneHandlerForMessageException(sprintf('More than one handler for message "%s".', \get_class($message)));
        }

        return $next($message);
    }
}
