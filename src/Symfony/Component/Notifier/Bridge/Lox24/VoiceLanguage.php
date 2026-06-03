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

enum VoiceLanguage: string
{
    case German = 'DE';
    case English = 'EN';
    case Spanish = 'ES';
    case French = 'FR';
    case Italian = 'IT';
    case Auto = 'auto';
}
