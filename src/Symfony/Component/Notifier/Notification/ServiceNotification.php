<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Notification;

use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Contracts\Service\ResetInterface;

abstract class ServiceNotification extends Notification implements ResetInterface
{
    protected $subject;
    private $notifier;

    protected function __construct(string $subject = '', array $channels = [])
    {
        parent::__construct($subject, $channels);
    }

    public function __invoke($subject)
    {
        $this->subject = $subject;

        try {
            foreach ($this->recipients() as $recipient) {
                $this->notifier->send($this, $recipient);
            }
        } finally {
            $this->reset();
        }
    }

    /**
     * @internal
     */
    public function setNotifier(NotifierInterface $notifier): void
    {
        $this->notifier = $notifier;
    }

    public function reset()
    {
        $this->subject = null;
    }

    /**
     * @return iterable|Recipient[]
     */
    abstract protected function recipients(): iterable;
}
