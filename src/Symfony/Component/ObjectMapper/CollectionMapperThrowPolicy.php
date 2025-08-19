<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

enum CollectionMapperExceptionPolicy: string
{
    case FAIL_EARLY = 'fail_early';
    case FAIL_SAFE = 'fail_safe';
    case IGNORE_ALL_ERRORS = 'ignore_all_errors';

    public static function getDescription(self $self): string
    {
        return match ($self) {
            self::FAIL_EARLY => 'Stop at first error.',
            self::FAIL_SAFE => 'Collect all errors, then throw.',
            self::IGNORE_ALL_ERRORS => 'Continue processing, ignore all errors.'
        };
    }
}
