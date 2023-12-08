<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Event;

use Symfony\Component\Mime\Email;

class PostmarkDeliveryEventFactory
{
    public function create(int $errorCode, string $message, Email $email): PostmarkDeliveryEvent
    {
        if (!$this->supports($errorCode)) {
            throw new \InvalidArgumentException(sprintf('Error code "%s" is not supported.', $errorCode));
        }

        return (new PostmarkDeliveryEvent($message, $errorCode))
            ->setHeaders($email->getHeaders());
    }

    public function supports(int $errorCode): bool
    {
        return \in_array($errorCode, [
            PostmarkDeliveryEvent::CODE_INACTIVE_RECIPIENT,
        ]);
    }
}
