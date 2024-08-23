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

/**
 * Interface for all CallableWrapper attributes.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @experimental
 */
interface CallableWrapperAttributeInterface
{
    /**
     * @return string the class or id of the wrapper associated with this attribute
     */
    public function wrappedBy(): string;
}
