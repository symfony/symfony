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
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Middleware that add stamps configured on message.
 *
 * @author Kerian Montes <kerianmontes@gmail.com>
 */
class AutoStampMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $class = new \ReflectionClass($envelope->getMessage());
        do {
            foreach ($class->getAttributes(StampInterface::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $envelope = $envelope->with($attribute->newInstance());
            }
        } while ($class = $class->getParentClass());

        return $stack->next()->handle($envelope, $stack);
    }
}
