<?php

namespace Symfony\Component\Notifier\Bridge\Amazon;

use AsyncAws\Sns\Input\PublishInput;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Adrien Chinour <github@chinour.fr>
 *
 * @experimental in 5.3
 */
class AmazonSnsOptions implements MessageOptionsInterface
{
    /** @var string */
    private $topic;

    /**
     * @var array
     * @see PublishInput
     */
    private $options;

    public function __construct(string $topic, array $options = [])
    {
        $this->topic = $topic;
        $this->options = $options;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return $this->topic;
    }
}
