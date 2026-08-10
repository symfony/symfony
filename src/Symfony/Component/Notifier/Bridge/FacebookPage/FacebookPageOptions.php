<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FacebookPage;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * Optional Facebook Page feed options.
 *
 * Personal-profile publishing is not supported by the Graph API.
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class FacebookPageOptions implements MessageOptionsInterface
{
    private ?string $link = null;

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * Attach a URL preview to the Page post (Graph `link` parameter).
     */
    public function link(string $url): static
    {
        $this->link = $url;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function toArray(): array
    {
        return array_filter([
            'link' => $this->link,
        ], static fn ($value) => null !== $value && '' !== $value);
    }
}
