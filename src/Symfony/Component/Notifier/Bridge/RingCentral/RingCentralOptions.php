<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RingCentral;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class RingCentralOptions implements MessageOptionsInterface
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
    public function country(string $id, ?string $isoCode = null, ?string $name = null, ?string $uri = null, ?string $callingCode = null): static
    {
        $this->options['country'] = [
            'id' => $id,
            'isoCode' => $isoCode,
            'name' => $name,
            'uri' => $uri,
            'callingCode' => $callingCode,
        ];

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
