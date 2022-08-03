<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord\Embeds;

/**
 * @author Karoly Gossler <connor@connor.hu>
 */
final class DiscordEmbed extends AbstractDiscordEmbed
{
    /**
     * @return $this
     */
    public function title(string $title): static
    {
        $this->options['title'] = $title;

        return $this;
    }

    /**
     * @return $this
     */
    public function description(string $description): static
    {
        $this->options['description'] = $description;

        return $this;
    }

    /**
     * @return $this
     */
    public function url(string $url): static
    {
        $this->options['url'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function timestamp(\DateTime $timestamp): static
    {
        $this->options['timestamp'] = $timestamp->format(\DateTimeInterface::ISO8601);

        return $this;
    }

    /**
     * @return $this
     */
    public function color(int $color): static
    {
        $this->options['color'] = $color;

        return $this;
    }

    /**
     * @return $this
     */
    public function footer(DiscordFooterEmbedObject $footer): static
    {
        $this->options['footer'] = $footer->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function thumbnail(DiscordMediaEmbedObject $thumbnail): static
    {
        $this->options['thumbnail'] = $thumbnail->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function image(DiscordMediaEmbedObject $image): static
    {
        $this->options['image'] = $image->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function author(DiscordAuthorEmbedObject $author): static
    {
        $this->options['author'] = $author->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function addField(DiscordFieldEmbedObject $field): static
    {
        if (!isset($this->options['fields'])) {
            $this->options['fields'] = [];
        }

        $this->options['fields'][] = $field->toArray();

        return $this;
    }
}
