<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * DecorationPriorityAwareInterface sets the decoration_priority value for a class implementing DecoratorInterface.
 *
 * @author Grégory SURACI <gregory.suraci@free.fr>
 */
interface DecorationPriorityAwareInterface
{
    /**
     * @return int the decoration priority
     */
    public static function getDecorationPriority(): int;
}
