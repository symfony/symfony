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
 * DecoratorInterface defines a decorator class without configuration.
 *
 * @author Grégory SURACI <gregory.suraci@free.fr>
 */
interface DecoratorInterface
{
    /**
     * @return string the serviceId/FQCN that will be decorated by this interface's implementation
     */
    public static function getDecoratedServiceId(): string;
}
