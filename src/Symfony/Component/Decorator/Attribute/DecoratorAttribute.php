<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator\Attribute;

/**
 * Abstract class for all decorator attributes.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @experimental
 */
abstract class DecoratorAttribute
{
    public function decoratedBy(): string
    {
        return static::class.'Decorator';
    }
}
