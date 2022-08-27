<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RemoteEvent\Event\Mailer;

use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
abstract class AbstractMailerEvent extends RemoteEvent
{
    private \DateTimeImmutable $date;
    private string $email = '';
    private array $metadata = [];
    private array $tags = [];

    public function setDate(\DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setRecipientEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getRecipientEmail(): string
    {
        return $this->email;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
