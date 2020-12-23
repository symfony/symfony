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
 */
final class VisibilityShare extends AbstractLinkedInShare
{
    public const MEMBER_NETWORK_VISIBILITY = 'MemberNetworkVisibility';
    public const SPONSORED_CONTENT_VISIBILITY = 'SponsoredContentVisibility';

    public const CONNECTIONS = 'CONNECTIONS';
    public const PUBLIC = 'PUBLIC';
    public const LOGGED_IN = 'LOGGED_IN';
    public const DARK = 'DARK';

    private const MEMBER_NETWORK = [
        self::CONNECTIONS,
        self::PUBLIC,
        self::LOGGED_IN,
    ];

    private const AVAILABLE_VISIBILITY = [
        self::MEMBER_NETWORK_VISIBILITY,
        self::SPONSORED_CONTENT_VISIBILITY,
    ];

    public function __construct(string $visibility = self::MEMBER_NETWORK_VISIBILITY, string $value = 'PUBLIC')
    {
        if (!\in_array($visibility, self::AVAILABLE_VISIBILITY)) {
            throw new LogicException(sprintf('"%s" is not a valid visibility, available visibility are "%s".', $visibility, implode(', ', self::AVAILABLE_VISIBILITY)));
        }

        if (self::MEMBER_NETWORK_VISIBILITY === $visibility && !\in_array($value, self::MEMBER_NETWORK)) {
            throw new LogicException(sprintf('"%s" is not a valid value, available value for visibility "%s" are "%s".', $value, $visibility, implode(', ', self::MEMBER_NETWORK)));
        }

        if (self::SPONSORED_CONTENT_VISIBILITY === $visibility && self::DARK !== $value) {
            throw new LogicException(sprintf('"%s" is not a valid value, available value for visibility "%s" is "%s".', $value, $visibility, self::DARK));
        }

        $this->options['com.linkedin.ugc.'.$visibility] = $value;
    }
}
