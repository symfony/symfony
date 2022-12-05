<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twitter;

use Symfony\Component\Mime\Part\File;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class TwitterOptions implements MessageOptionsInterface
{
    public const REPLY_MENTIONED_USERS = 'mentionedUsers';
    public const REPLY_FOLLOWING = 'following';

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
     * @param string[] $choices
     *
     * @return $this
     */
    public function poll(array $choices, int $duration): static
    {
        $this->options['poll'] = [
            'options' => $choices,
            'duration_minutes' => $duration,
        ];

        return $this;
    }

    /**
     * @return $this
     */
    public function quote(string $tweetId): static
    {
        $this->options['quote_tweet_id'] = $tweetId;

        return $this;
    }

    /**
     * @param string[] $excludedUserIds
     *
     * @return $this
     */
    public function inReplyTo(string $tweetId, array $excludedUserIds = []): static
    {
        $this->options['reply']['in_reply_to_tweet_id'] = $tweetId;
        $this->options['reply']['exclude_reply_user_ids'] = $excludedUserIds;

        return $this;
    }

    /**
     * @param string[] $extraOwners
     *
     * @return $this
     */
    public function attachImage(File $file, string $alt = '', array $extraOwners = []): static
    {
        $this->options['attach'][] = [
            'file' => $file,
            'alt' => $alt,
            'subtitles' => null,
            'category' => 'tweet_image',
            'owners' => $extraOwners,
        ];

        return $this;
    }

    /**
     * @param string[] $extraOwners
     *
     * @return $this
     */
    public function attachGif(File $file, string $alt = '', array $extraOwners = []): static
    {
        $this->options['attach'][] = [
            'file' => $file,
            'alt' => $alt,
            'subtitles' => null,
            'category' => 'tweet_gif',
            'owners' => $extraOwners,
        ];

        return $this;
    }

    /**
     * @param File|null $subtitles   File should be named as "display_name.language_code.srt"
     * @param string[]  $extraOwners
     *
     * @return $this
     */
    public function attachVideo(File $file, string $alt = '', File $subtitles = null, bool $amplify = false, array $extraOwners = []): static
    {
        $this->options['attach'][] = [
            'file' => $file,
            'alt' => $alt,
            'subtitles' => $subtitles,
            'category' => $amplify ? 'amplify_video' : 'tweet_video',
            'owners' => $extraOwners,
        ];

        return $this;
    }

    /**
     * @param self::REPLY_* $settings
     *
     * @return $this
     */
    public function replySettings(string $settings): static
    {
        $this->options['reply_settings'] = $settings;

        return $this;
    }

    /**
     * @param string[] $userIds
     *
     * @return $this
     */
    public function taggedUsers(array $userIds): static
    {
        $this->options['media']['tagged_user_ids'] = $userIds;

        return $this;
    }

    /**
     * @return $this
     */
    public function deepLink(string $url): static
    {
        $this->options['direct_message_deep_link'] = $url;

        return $this;
    }

    /**
     * @see https://developer.twitter.com/en/docs/twitter-api/v1/geo/places-near-location/api-reference/get-geo-search
     *
     * @return $this
     */
    public function place(string $placeId): static
    {
        $this->options['geo']['place_id'] = $placeId;

        return $this;
    }

    /**
     * @return $this
     */
    public function forSuperFollowersOnly(bool $flag = true): static
    {
        $this->options['for_super_followers_only'] = $flag;

        return $this;
    }
}
