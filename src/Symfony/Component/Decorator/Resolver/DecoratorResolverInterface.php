<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator\Resolver;

use Symfony\Component\Decorator\Attribute\DecoratorAttribute;
use Symfony\Component\Decorator\DecoratorInterface;

/**
 * Resolves the decorator linked to a given decorator metadata.
 */
interface DecoratorResolverInterface
{
    public function resolve(DecoratorAttribute $metadata): DecoratorInterface;
}
