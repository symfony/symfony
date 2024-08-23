<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CallableWrapper\Attribute;

use Symfony\Component\CallableWrapper\CallableWrapperInterface;

/**
 * Abstract class for all CallableWrapper attributes.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @experimental
 */
abstract class CallableWrapperAttribute implements CallableWrapperAttributeInterface
{
    public function wrappedBy(): string
    {
        if ($this instanceof CallableWrapperInterface) {
            return $this::class;
        }

        return $this::class.'CallableWrapper';
    }
}
