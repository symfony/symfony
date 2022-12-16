<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack;

use Symfony\Component\Notifier\Bridge\Slack\Block\SlackBlockInterface;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackDividerBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackSectionBlock;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SlackOptions implements MessageOptionsInterface
{
    private const MAX_BLOCKS = 50;

    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;

        if (\count($this->options['blocks'] ?? []) > self::MAX_BLOCKS) {
            throw new LogicException(sprintf('Maximum number of "blocks" has been reached (%d).', self::MAX_BLOCKS));
        }
    }

    public static function fromNotification(Notification $notification): self
    {
        $options = new self();
        $options->iconEmoji($notification->getEmoji());
        $options->block((new SlackSectionBlock())->text($notification->getSubject()));
        if ($notification->getContent()) {
            $options->block((new SlackSectionBlock())->text($notification->getContent()));
        }
        if ($exception = $notification->getExceptionAsString()) {
            $options->block(new SlackDividerBlock());
            $options->block((new SlackSectionBlock())->text($exception));
        }

        return $options;
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
     * @param string $id The hook id (anything after https://hooks.slack.com/services/)
     *
     * @return $this
     */
    public function recipient(string $id): static
    {
        $this->options['recipient_id'] = $id;

        return $this;
    }

    /**
     * @return $this
     */
    public function asUser(bool $bool): static
    {
        $this->options['as_user'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function block(SlackBlockInterface $block): static
    {
        if (\count($this->options['blocks'] ?? []) >= self::MAX_BLOCKS) {
            throw new LogicException(sprintf('Maximum number of "blocks" has been reached (%d).', self::MAX_BLOCKS));
        }

        $this->options['blocks'][] = $block->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function iconEmoji(string $emoji): static
    {
        $this->options['icon_emoji'] = $emoji;

        return $this;
    }

    /**
     * @return $this
     */
    public function iconUrl(string $url): static
    {
        $this->options['icon_url'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function linkNames(bool $bool): static
    {
        $this->options['link_names'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function mrkdwn(bool $bool): static
    {
        $this->options['mrkdwn'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function parse(string $parse): static
    {
        $this->options['parse'] = $parse;

        return $this;
    }

    /**
     * @return $this
     */
    public function unfurlLinks(bool $bool): static
    {
        $this->options['unfurl_links'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function unfurlMedia(bool $bool): static
    {
        $this->options['unfurl_media'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function username(string $username): static
    {
        $this->options['username'] = $username;

        return $this;
    }

    /**
     * @return $this
     */
    public function threadTs(string $threadTs): static
    {
        $this->options['thread_ts'] = $threadTs;

        return $this;
    }
}
