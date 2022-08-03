<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AmazonSns;

use AsyncAws\Sns\Input\PublishInput;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Adrien Chinour <github@chinour.fr>
 */
final class AmazonSnsOptions implements MessageOptionsInterface
{
    private array $options = [];
    private string $recipient;

    public function __construct(string $recipient, array $options = [])
    {
        $this->recipient = $recipient;
        $this->options = $options;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return $this->recipient;
    }

    /**
     * @param string $topic The Topic ARN for SNS message
     *
     * @return $this
     */
    public function recipient(string $topic): static
    {
        $this->recipient = $topic;

        return $this;
    }

    /**
     * @see PublishInput::$Subject
     *
     * @return $this
     */
    public function subject(string $subject): static
    {
        $this->options['Subject'] = $subject;

        return $this;
    }

    /**
     * @see PublishInput::$MessageStructure
     *
     * @return $this
     */
    public function messageStructure(string $messageStructure): static
    {
        $this->options['MessageStructure'] = $messageStructure;

        return $this;
    }
}
