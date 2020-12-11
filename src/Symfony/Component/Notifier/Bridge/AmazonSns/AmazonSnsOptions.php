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
 *
 * @experimental in 5.3
 */
final class AmazonSnsOptions implements MessageOptionsInterface
{
    private $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function toArray(): array
    {
        $options = $this->options;
        unset($options['recipient_id']);

        return $options;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    /**
     * @param string $topic The Topic ARN for SNS message
     *
     * @return $this
     */
    public function recipient(string $topic): self
    {
        $this->options['recipient_id'] = $topic;

        return $this;
    }

    /**
     * @see PublishInput::$Subject
     */
    public function subject(string $subject): self
    {
        $this->options['Subject'] = $subject;

        return $this;
    }

    /**
     * @see PublishInput::$MessageStructure
     */
    public function messageStructure(string $messageStructure): self
    {
        $this->options['MessageStructure'] = $messageStructure;

        return $this;
    }

    /**
     * @see PublishInput::$MessageAttributes
     */
    public function messageAttributes(array $messageAttributes): self
    {
        $this->options['MessageAttributes'] = $messageAttributes;

        return $this;
    }

    /**
     * @see PublishInput::$MessageDeduplicationId
     */
    public function messageDeduplicationId(string $messageDeduplicationId): self
    {
        $this->options['MessageDeduplicationId'] = $messageDeduplicationId;

        return $this;
    }

    /**
     * @see PublishInput::$MessageGroupId
     */
    public function messageGroupId(string $messageGroupId): self
    {
        $this->options['MessageGroupId'] = $messageGroupId;

        return $this;
    }
}
