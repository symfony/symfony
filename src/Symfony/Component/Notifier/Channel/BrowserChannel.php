<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Channel;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Notifier\FlashMessage\DefaultFlashMessageImportanceMapper;
use Symfony\Component\Notifier\FlashMessage\FlashMessageImportanceMapperInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class BrowserChannel implements ChannelInterface
{
    private RequestStack $stack;

    private FlashMessageImportanceMapperInterface $mapper;

    public function __construct(RequestStack $stack, FlashMessageImportanceMapperInterface $mapper = new DefaultFlashMessageImportanceMapper())
    {
        $this->stack = $stack;
        $this->mapper = $mapper;
    }

    public function notify(Notification $notification, RecipientInterface $recipient, string $transportName = null): void
    {
        if (null === $request = $this->stack->getCurrentRequest()) {
            return;
        }

        $message = $notification->getSubject();
        if ($notification->getEmoji()) {
            $message = $notification->getEmoji().' '.$message;
        }
        $request->getSession()->getFlashBag()->add($this->mapper->flashMessageTypeFromImportance($notification->getImportance()), $message);
    }

    public function supports(Notification $notification, RecipientInterface $recipient): bool
    {
        return true;
    }
}
