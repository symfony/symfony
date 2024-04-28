<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Bluesky;

use Symfony\Component\Mime\Part\File;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

final class BlueskyOptions implements MessageOptionsInterface
{
    public function __construct(
        private array $options = [],
    ) {
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function attachMedia(File $file, string $description = ''): static
    {
        $this->options['attach'][] = [
            'file' => $file,
            'description' => $description,
        ];

        return $this;
    }
}
