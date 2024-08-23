<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CallableWrapper\Resolver;

use Symfony\Component\CallableWrapper\Attribute\CallableWrapperAttributeInterface;
use Symfony\Component\CallableWrapper\CallableWrapperInterface;

/**
 * Resolves the wrapper linked to a given wrapper attribute.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface CallableWrapperResolverInterface
{
    public function resolve(CallableWrapperAttributeInterface $attribute): CallableWrapperInterface;
}
