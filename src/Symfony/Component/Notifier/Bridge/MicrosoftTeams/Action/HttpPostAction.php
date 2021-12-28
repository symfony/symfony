<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Element\Header;

/**
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @see https://docs.microsoft.com/en-us/outlook/actionable-messages/message-card-reference#httppost-action
 */
final class HttpPostAction implements ActionCardCompatibleActionInterface
{
    private $options = ['@type' => 'HttpPOST'];

    /**
     * @return $this
     */
    public function name(string $name): self
    {
        $this->options['name'] = $name;

        return $this;
    }

    /**
     * @return $this
     */
    public function target(string $url): self
    {
        $this->options['target'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function header(Header $header): self
    {
        $this->options['headers'][] = $header->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function body(string $body): self
    {
        $this->options['body'] = $body;

        return $this;
    }

    /**
     * @return $this
     */
    public function bodyContentType(string $contentType): self
    {
        $this->options['bodyContentType'] = $contentType;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
