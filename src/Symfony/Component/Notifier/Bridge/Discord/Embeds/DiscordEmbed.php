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
    public function title(string $title): self
    {
        $this->options['title'] = $title;

        return $this;
    }

    public function description(string $description): self
    {
        $this->options['description'] = $description;

        return $this;
    }

    public function url(string $url): self
    {
        $this->options['url'] = $url;

        return $this;
    }

    public function timestamp(\DateTime $timestamp): self
    {
        $this->options['timestamp'] = $timestamp->format(\DateTimeInterface::ISO8601);

        return $this;
    }

    public function color(int $color): self
    {
        $this->options['color'] = $color;

        return $this;
    }

    public function footer(DiscordFooterEmbedObject $footer): self
    {
        $this->options['footer'] = $footer->toArray();

        return $this;
    }

    public function thumbnail(DiscordMediaEmbedObject $thumbnail): self
    {
        $this->options['thumbnail'] = $thumbnail->toArray();

        return $this;
    }

    public function image(DiscordMediaEmbedObject $image): self
    {
        $this->options['image'] = $image->toArray();

        return $this;
    }

    public function author(DiscordAuthorEmbedObject $author): self
    {
        $this->options['author'] = $author->toArray();

        return $this;
    }

    public function addField(DiscordFieldEmbedObject $field): self
    {
        if (!isset($this->options['fields'])) {
            $this->options['fields'] = [];
        }

        $this->options['fields'][] = $field->toArray();

        return $this;
    }
}
