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
use Symfony\Component\Messenger\Exception\UnauthorizedException;
use Symfony\Component\Messenger\Stamp\AuthorizationAttributeStamp;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @author Maxime Perrimond <max.perrimond@gmail.com>
 */
class AuthorizationCheckerMiddleware implements MiddlewareInterface
{
    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $attribute = null;
        /** @var AuthorizationAttributeStamp|null $stamp */
        if ($stamp = $envelope->last(AuthorizationAttributeStamp::class)) {
            $attribute = $stamp->getAttribute();
        }

        if ($attribute && !$this->authorizationChecker->isGranted($attribute, $message)) {
            throw new UnauthorizedException($attribute, $message);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
