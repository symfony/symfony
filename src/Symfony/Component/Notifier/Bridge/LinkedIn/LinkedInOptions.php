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
    private $options = [];

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

    public function contentCertificationRecord(string $contentCertificationRecord): self
    {
        $this->options['contentCertificationRecord'] = $contentCertificationRecord;

        return $this;
    }

    public function firstPublishedAt(int $firstPublishedAt): self
    {
        $this->options['firstPublishedAt'] = $firstPublishedAt;

        return $this;
    }

    public function lifecycleState(LifecycleStateShare $lifecycleStateOption): self
    {
        $this->options['lifecycleState'] = $lifecycleStateOption->lifecycleState();

        return $this;
    }

    public function origin(string $origin): self
    {
        $this->options['origin'] = $origin;

        return $this;
    }

    public function ugcOrigin(string $ugcOrigin): self
    {
        $this->options['ugcOrigin'] = $ugcOrigin;

        return $this;
    }

    public function versionTag(string $versionTag): self
    {
        $this->options['versionTag'] = $versionTag;

        return $this;
    }

    public function specificContent(ShareContentShare $specificContent): self
    {
        $this->options['specificContent']['com.linkedin.ugc.ShareContent'] = $specificContent->toArray();

        return $this;
    }

    public function author(AuthorShare $authorOption): self
    {
        $this->options['author'] = $authorOption->author();

        return $this;
    }

    public function visibility(VisibilityShare $visibilityOption): self
    {
        $this->options['visibility'] = $visibilityOption->toArray();

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->options['author'] ?? null;
    }
}
