<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ContactEveryone;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class ContactEveryoneOptions implements MessageOptionsInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function diffusionName(string $diffusionName): static
    {
        $this->options['diffusion_name'] = $diffusionName;

        return $this;
    }

    /**
     * @return $this
     */
    public function category(string $category): static
    {
        $this->options['category'] = $category;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
