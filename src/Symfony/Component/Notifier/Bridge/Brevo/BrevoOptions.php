<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Brevo;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

final class BrevoOptions implements MessageOptionsInterface
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
    public function webUrl(string $url): static
    {
        $this->options['webUrl'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function type(string $type = 'transactional'): static
    {
        $this->options['type'] = $type;

        return $this;
    }

    /**
     * @return $this
     */
    public function tag(string $tag): static
    {
        $this->options['tag'] = $tag;

        return $this;
    }
}
