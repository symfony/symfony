<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Attribute\ArgumentResolver;

use Symfony\Component\EventDispatcher\Attribute\ArgumentResolverInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Resolves argument for event listener.
 *
 * @author Kerian MONTES <kerianmontes@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class GenericEventSubjectResolver implements ArgumentResolverInterface
{
    public function resolve(object $event): mixed
    {
        if (!$event instanceof GenericEvent) {
            throw new \InvalidArgumentException(sprintf('Argument must be an instance of "%s", "%s" given.', GenericEvent::class, $event::class));
        }

        return $event->getSubject();
    }
}
