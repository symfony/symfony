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

use Symfony\Component\Notifier\Exception\InvalidArgumentException;

/**
 * LinkedIn UGC author entity type (person profile or company page).
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
enum LinkedInAuthorType: string
{
    case Person = 'person';
    case Organization = 'organization';

    public static function fromDsnOption(?string $value): self
    {
        if (null === $value || '' === $value) {
            return self::Person;
        }

        return self::tryFrom($value) ?? throw new InvalidArgumentException(\sprintf('Invalid LinkedIn DSN author option "%s". Supported values: "person", "organization".', $value));
    }
}
