<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Lox24;

enum Type: string
{
    case Sms = 'sms';
    case Voice = 'voice';

    public function getServiceCode(): string
    {
        return match ($this) {
            self::Sms => 'direct',
            self::Voice => 'text2speech',
        };
    }
}
