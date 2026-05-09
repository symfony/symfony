<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

final class SessionHasFlashMessage extends Constraint
{
    public function __construct(
        private readonly string $messageType,
        private readonly mixed $messages,
    ) {
    }

    public function toString(): string
    {
        return \sprintf('session has flash message of type "%s" containing: %s', $this->messageType, implode(', ', $this->getExpectedMessages()));
    }

    protected function getExpectedMessages(): array
    {
        return \is_array($this->messages) ? $this->messages : [(string) $this->messages];
    }

    protected function matches(mixed $other): bool
    {
        if (!$other instanceof Request) {
            return false;
        }

        if (!$other->hasSession()) {
            return false;
        }

        $session = $other->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return false;
        }

        $flashbag = $session->getFlashBag();
        $flashMessages = $flashbag->peek($this->messageType);
        $expectedMessages = $this->getExpectedMessages();

        return array_any($flashMessages, static fn (mixed $message) => \in_array($message, $expectedMessages, true));
    }

    protected function failureDescription(mixed $other): string
    {
        if (!$other instanceof Request) {
            return 'because the constraint was not configured with a Request object';
        }

        $message = $this->toString();

        if (!$other->hasSession()) {
            return $message.', because the Request does not have a Session';
        }

        $session = $other->getSession();
        if (!$session instanceof FlashBagAwareSessionInterface) {
            return $message.', because the Session does not have a FlashBag';
        }

        return $message;
    }
}
