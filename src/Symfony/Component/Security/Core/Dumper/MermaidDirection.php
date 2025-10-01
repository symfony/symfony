<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Dumper;

enum MermaidDirection: string
{
    case TOP_TO_BOTTOM = 'TB';
    case TOP_DOWN = 'TD';
    case BOTTOM_TO_TOP = 'BT';
    case RIGHT_TO_LEFT = 'RL';
    case LEFT_TO_RIGHT = 'LR';
}
