<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\RememberMe;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeDetails;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;

/**
 * Used as a "workaround" for tagging aliases in the RememberMeFactory.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
final class DecoratedRememberMeHandler implements RememberMeHandlerInterface
{
    private RememberMeHandlerInterface $handler;

    public function __construct(RememberMeHandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function createRememberMeCookie(UserInterface $user): void
    {
        $this->handler->createRememberMeCookie($user);
    }

    /**
     * {@inheritDoc}
     */
    public function consumeRememberMeCookie(RememberMeDetails $rememberMeDetails): UserInterface
    {
        return $this->handler->consumeRememberMeCookie($rememberMeDetails);
    }

    /**
     * {@inheritDoc}
     */
    public function clearRememberMeCookie(): void
    {
        $this->handler->clearRememberMeCookie();
    }
}
