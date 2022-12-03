<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\ActionInterface;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Section;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\SectionInterface;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @see https://docs.microsoft.com/en-us/outlook/actionable-messages/message-card-reference
 */
final class MicrosoftTeamsOptions implements MessageOptionsInterface
{
    private const MAX_ACTIONS = 4;

    private array $options = [];

    public function __construct(array $options = [])
    {
        if (\array_key_exists('themeColor', $options)) {
            $this->validateThemeColor($options['themeColor']);
        }

        $this->options = $options;

        $this->validateNumberOfActions();
    }

    public static function fromNotification(Notification $notification): self
    {
        $options = (new self())
            ->title($notification->getSubject())
            ->text($notification->getContent());

        if ($exception = $notification->getExceptionAsString()) {
            $options->section((new Section())->text($exception));
        }

        return $options;
    }

    public function toArray(): array
    {
        $options = $this->options;

        // Send a text, not a message card
        if (1 === \count($options) && isset($options['text'])) {
            return $options;
        }

        $options['@type'] = 'MessageCard';
        $options['@context'] = 'https://schema.org/extensions';

        return $options;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    /**
     * @param string $path The hook path (anything after https://outlook.office.com)
     *
     * @return $this
     */
    public function recipient(string $path): static
    {
        if (!preg_match('/^\/webhookb2\//', $path)) {
            throw new InvalidArgumentException(sprintf('"%s" require recipient id format to be "/webhookb2/{uuid}@{uuid}/IncomingWebhook/{id}/{uuid}", "%s" given.', __CLASS__, $path));
        }

        $this->options['recipient_id'] = $path;

        return $this;
    }

    /**
     * @param string $summary Markdown string
     *
     * @return $this
     */
    public function summary(string $summary): static
    {
        $this->options['summary'] = $summary;

        return $this;
    }

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
    public function text(string $text): static
    {
        $this->options['text'] = $text;

        return $this;
    }

    /**
     * @return $this
     */
    public function themeColor(string $themeColor): static
    {
        $this->validateThemeColor($themeColor);

        $this->options['themeColor'] = $themeColor;

        return $this;
    }

    /**
     * @return $this
     */
    public function section(SectionInterface $section): static
    {
        $this->options['sections'][] = $section->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function action(ActionInterface $action): static
    {
        $this->validateNumberOfActions();

        $this->options['potentialAction'][] = $action->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function expectedActor(string $actor): static
    {
        $this->options['expectedActors'][] = $actor;

        return $this;
    }

    private function validateNumberOfActions(): void
    {
        if (\count($this->options['potentialAction'] ?? []) >= self::MAX_ACTIONS) {
            throw new InvalidArgumentException(sprintf('MessageCard maximum number of "potentialAction" has been reached (%d).', self::MAX_ACTIONS));
        }
    }

    private function validateThemeColor(string $themeColor): void
    {
        if (!preg_match('/^#([0-9a-f]{6}|[0-9a-f]{3})$/i', $themeColor)) {
            throw new InvalidArgumentException('MessageCard themeColor must have a valid hex color format.');
        }
    }
}
