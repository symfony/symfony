<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LinkedIn\Share;

use Symfony\Component\Notifier\Exception\LogicException;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 *
 * @see https://docs.microsoft.com/en-us/linkedin/marketing/integrations/community-management/shares/ugc-post-api#schema lifecycleState section
 */
final class LifecycleStateShare extends AbstractLinkedInShare
{
    public const DRAFT = 'DRAFT';
    public const PUBLISHED = 'PUBLISHED';
    public const PROCESSING = 'PROCESSING';
    public const PROCESSING_FAILED = 'PROCESSING_FAILED';
    public const DELETED = 'DELETED';
    public const PUBLISHED_EDITED = 'PUBLISHED_EDITED';

    private const AVAILABLE_LIFECYCLE = [
        self::DRAFT,
        self::PUBLISHED,
        self::PROCESSING_FAILED,
        self::DELETED,
        self::PROCESSING_FAILED,
        self::PUBLISHED_EDITED,
    ];

    private string $lifecycleState;

    public function __construct(string $lifecycleState = self::PUBLISHED)
    {
        if (!\in_array($lifecycleState, self::AVAILABLE_LIFECYCLE)) {
            throw new LogicException(sprintf('"%s" is not a valid value, available lifecycle are "%s".', $lifecycleState, implode(', ', self::AVAILABLE_LIFECYCLE)));
        }

        $this->lifecycleState = $lifecycleState;
    }

    public function lifecycleState(): string
    {
        return $this->lifecycleState;
    }
}
