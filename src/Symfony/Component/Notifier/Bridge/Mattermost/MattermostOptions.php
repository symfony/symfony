<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mattermost;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Nathanaël Martel <nat@nathanaelmartel.net>
 */
final class MattermostOptions implements MessageOptionsInterface
{
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function recipient(string $id): self
    {
        $this->options['recipient_id'] = $id;

        return $this;
    }

    public function toArray(): array
    {
        $options = $this->options;
        unset($options['recipient_id']);

        return $options;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }
}
