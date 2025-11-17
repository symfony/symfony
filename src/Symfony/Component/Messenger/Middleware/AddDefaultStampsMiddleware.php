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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Message\DefaultStampsProviderInterface;

/**
 * Automatically add stamps from the DefaultStampsProviderInterface.
 */
class AddDefaultStampsMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        if ($message instanceof DefaultStampsProviderInterface) {
            foreach ($message->getDefaultStamps() as $stamp) {
                if (null === $envelope->last($stamp::class)) {
                    $envelope = $envelope->with($stamp);
                }
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
