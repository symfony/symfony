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
 * @see https://docs.microsoft.com/en-us/linkedin/marketing/integrations/community-management/shares/ugc-post-api#sharecontent
 */
final class ShareContentShare extends AbstractLinkedInShare
{
    public const ARTICLE = 'ARTICLE';
    public const IMAGE = 'IMAGE';
    public const NONE = 'NONE';
    public const RICH = 'RICH';
    public const VIDEO = 'VIDEO';
    public const LEARNING_COURSE = 'LEARNING_COURSE';
    public const JOB = 'JOB';
    public const QUESTION = 'QUESTION';
    public const ANSWER = 'ANSWER';
    public const CAROUSEL = 'CAROUSEL';
    public const TOPIC = 'TOPIC';
    public const NATIVE_DOCUMENT = 'NATIVE_DOCUMENT';
    public const URN_REFERENCE = 'URN_REFERENCE';
    public const LIVE_VIDEO = 'LIVE_VIDEO';

    public const ALL = [
        self::ARTICLE,
        self::IMAGE,
        self::NONE,
        self::RICH,
        self::VIDEO,
        self::LEARNING_COURSE,
        self::JOB,
        self::QUESTION,
        self::ANSWER,
        self::CAROUSEL,
        self::TOPIC,
        self::NATIVE_DOCUMENT,
        self::URN_REFERENCE,
        self::LIVE_VIDEO,
    ];

    public function __construct(string $text, array $attributes = [], string $inferredLocale = null, ShareMediaShare $media = null, string $primaryLandingPageUrl = null, string $shareMediaCategory = self::NONE)
    {
        $this->options['shareCommentary'] = [
            'attributes' => $attributes,
            'text' => $text,
        ];

        if (null !== $inferredLocale) {
            $this->options['shareCommentary']['inferredLocale'] = $inferredLocale;
        }

        if (null !== $media) {
            $this->options['media'] = $media->toArray();
        }

        if (null !== $primaryLandingPageUrl) {
            $this->options['primaryLandingPageUrl'] = $primaryLandingPageUrl;
        }

        if ($shareMediaCategory) {
            if (!\in_array($shareMediaCategory, self::ALL)) {
                throw new LogicException(sprintf('"%s" is not valid option, available options are "%s".', $shareMediaCategory, implode(', ', self::ALL)));
            }

            $this->options['shareMediaCategory'] = $shareMediaCategory;
        }
    }
}
