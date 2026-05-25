<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Prelude;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Imad Zairig <imadzairig@gmail.com>
 */
final class PreludeOptions implements MessageOptionsInterface
{
    public function __construct(
        private array $options = [],
    ) {
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function templateId(string $templateId): static
    {
        $this->options['template_id'] = $templateId;

        return $this;
    }

    /**
     * @return $this
     */
    public function variables(array $variables): static
    {
        $this->options['variables'] = $variables;

        return $this;
    }

    /**
     * @return $this
     */
    public function from(string $from): static
    {
        $this->options['from'] = $from;

        return $this;
    }

    /**
     * @return $this
     */
    public function locale(string $locale): static
    {
        $this->options['locale'] = $locale;

        return $this;
    }

    /**
     * @return $this
     */
    public function expiresAt(string $expiresAt): static
    {
        $this->options['expires_at'] = $expiresAt;

        return $this;
    }

    /**
     * @return $this
     */
    public function scheduleAt(string $scheduleAt): static
    {
        $this->options['schedule_at'] = $scheduleAt;

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

    /**
     * @return $this
     */
    public function correlationId(string $correlationId): static
    {
        $this->options['correlation_id'] = $correlationId;

        return $this;
    }

    /**
     * @return $this
     */
    public function preferredChannel(string $preferredChannel): static
    {
        $this->options['preferred_channel'] = $preferredChannel;

        return $this;
    }
}
