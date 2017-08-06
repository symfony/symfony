<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\Router;

use Symfony\Component\Worker\MessageCollection;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class RoundRobinRouter implements RouterInterface
{
    private $consumeEverything;
    private $cycle;
    private $mapping;

    public function __construct(array $routers, $consumeEverything = false)
    {
        if (!$routers) {
            throw new \LogicException('At least one routers should be set up.');
        }

        foreach ($routers as $router) {
            if (!$router instanceof RouterInterface) {
                throw new \LogicException('The item is not an instance of RouterInterface.');
            }
        }

        $this->cycle = new \InfiniteIterator(new \ArrayIterator($routers));
        $this->cycle->rewind();

        $this->consumeEverything = $consumeEverything;

        $this->mapping = new \SplObjectStorage();
    }

    public function fetchMessages()
    {
        $router = $this->cycle->current();

        $messageCollection = $router->fetchMessages();

        if (false !== $messageCollection && !$messageCollection instanceof MessageCollection) {
            throw new \RuntimeException('This is not a MessageCollection instance or false.');
        }

        if (false !== $messageCollection) {
            $this->mapping[$messageCollection] = $router;

            return $messageCollection;
        }

        // Try other fetcher, but stop the loop after one iteration
        while (($nextRouter = $this->next()) && $nextRouter !== $router) {
            $messageCollection = $nextRouter->fetchMessages();

            if (false !== $messageCollection) {
                $this->mapping[$messageCollection] = $nextRouter;

                return $messageCollection;
            }
        }

        return false;
    }

    public function consume(MessageCollection $messageCollection)
    {
        if (false === $this->consumeEverything) {
            $this->next();
        }

        $router = $this->mapping[$messageCollection];
        $this->mapping->detach($messageCollection);

        return $router->consume($messageCollection);
    }

    private function next()
    {
        $this->cycle->next();

        return $this->cycle->current();
    }
}
