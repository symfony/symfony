<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GatewayApi;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class GatewayApiOptions implements MessageOptionsInterface
{
    public function __construct(
        private array $options = [],
    ) {
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function class(string $class): static
    {
        $this->options['class'] = $class;

        return $this;
    }

    /**
     * @return $this
     */
    public function userRef(string $userRef): static
    {
        $this->options['userref'] = $userRef;

        return $this;
    }

    /**
     * @return $this
     */
    public function callbackUrl(string $callbackUrl): static
    {
        $this->options['callback_url'] = $callbackUrl;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
