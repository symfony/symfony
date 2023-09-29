<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Attribute;

/**
 * Resolves argument for event listener.
 *
 * @author Kerian MONTES <kerianmontes@gmail.com>
 */
interface ArgumentResolverInterface
{
    public function resolve(object $event): mixed;
}
