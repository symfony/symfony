<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LinkedIn;

use Symfony\Component\Notifier\Bridge\LinkedIn\Share\AuthorShare;
use Symfony\Component\Notifier\Bridge\LinkedIn\Share\LifecycleStateShare;
use Symfony\Component\Notifier\Bridge\LinkedIn\Share\ShareContentShare;
use Symfony\Component\Notifier\Bridge\LinkedIn\Share\VisibilityShare;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 */
final class LinkedInOptions implements MessageOptionsInterface
{
    private array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    public static function fromNotification(Notification $notification): self
    {
        $options = new self();
        $options->specificContent(new ShareContentShare($notification->getSubject()));

        if ($notification->getContent()) {
            $options->specificContent(new ShareContentShare($notification->getContent()));
        }

        $options->visibility(new VisibilityShare());
        $options->lifecycleState(new LifecycleStateShare());

        return $options;
    }

    /**
     * @return $this
     */
    public function contentCertificationRecord(string $contentCertificationRecord): static
    {
        $this->options['contentCertificationRecord'] = $contentCertificationRecord;

        return $this;
    }

    /**
     * @return $this
     */
    public function firstPublishedAt(int $firstPublishedAt): static
    {
        $this->options['firstPublishedAt'] = $firstPublishedAt;

        return $this;
    }

    /**
     * @return $this
     */
    public function lifecycleState(LifecycleStateShare $lifecycleStateOption): static
    {
        $this->options['lifecycleState'] = $lifecycleStateOption->lifecycleState();

        return $this;
    }

    /**
     * @return $this
     */
    public function origin(string $origin): static
    {
        $this->options['origin'] = $origin;

        return $this;
    }

    /**
     * @return $this
     */
    public function ugcOrigin(string $ugcOrigin): static
    {
        $this->options['ugcOrigin'] = $ugcOrigin;

        return $this;
    }

    /**
     * @return $this
     */
    public function versionTag(string $versionTag): static
    {
        $this->options['versionTag'] = $versionTag;

        return $this;
    }

    /**
     * @return $this
     */
    public function specificContent(ShareContentShare $specificContent): static
    {
        $this->options['specificContent']['com.linkedin.ugc.ShareContent'] = $specificContent->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function author(AuthorShare $authorOption): static
    {
        $this->options['author'] = $authorOption->author();

        return $this;
    }

    /**
     * @return $this
     */
    public function visibility(VisibilityShare $visibilityOption): static
    {
        $this->options['visibility'] = $visibilityOption->toArray();

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->options['author'] ?? null;
    }
}
