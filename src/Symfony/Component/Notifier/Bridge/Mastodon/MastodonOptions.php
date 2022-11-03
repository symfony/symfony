<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mastodon;

use Symfony\Component\Mime\Part\File;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

final class MastodonOptions implements MessageOptionsInterface
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
     * @param string[] $choices
     */
    public function poll(array $choices, int $expiresIn): self
    {
        $this->options['poll'] = [
            'options' => $choices,
            'expires_in' => $expiresIn,
        ];

        return $this;
    }

    public function attachMedia(File $file, File $thumbnail = null, string $description = null, string $focus = null): self
    {
        $this->options['attach'][] = [
            'file' => $file,
            'thumbnail' => $thumbnail,
            'description' => $description,
            'focus' => $focus,
        ];

        return $this;
    }
}
