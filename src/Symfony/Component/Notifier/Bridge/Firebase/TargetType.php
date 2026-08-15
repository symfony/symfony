<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase;

/**
 * @author Vojtech Smejkal <https://vojtechsmejkal.cz>
 */
enum TargetType: string
{
    case Topic = 'topic';
    case Token = 'token';
    case Condition = 'condition';
}
