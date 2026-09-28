<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Fixtures\ReturnedMessages;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'method-return-enum-draft',
            self::Published => 'method-return-enum-published',
        };
    }

    public static function fromSwitch(string $status): string
    {
        switch ($status) {
            case 'draft':
                return 'method-return-switch-draft';
            default:
                return 'method-return-switch-default';
        }
    }
}
