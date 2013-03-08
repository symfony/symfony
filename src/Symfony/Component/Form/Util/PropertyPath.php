<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

use Symfony\Component\PropertyAccess\PropertyPath as BasePropertyPath;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Alias for {@link \Symfony\Component\PropertyAccess\PropertyPath}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated deprecated since version 2.2, to be removed in 2.3. Use
 *             {@link \Symfony\Component\PropertyAccess\PropertyPath}
 *             instead.
 */
class PropertyPath extends BasePropertyPath
{
    /**
     * {@inheritdoc}
     */
    public function __construct($propertyPath)
    {
        parent::__construct($propertyPath);

        trigger_error('\Symfony\Component\Form\Util\PropertyPath is deprecated since version 2.2 and will be removed in 2.3. Use \Symfony\Component\PropertyAccess\PropertyPath instead.', E_USER_DEPRECATED);
    }

    /**
     * Alias for {@link PropertyAccessor::getValue()}
     */
    public function getValue($objectOrArray)
    {
        $propertyAccessor = PropertyAccess::getPropertyAccessor();

        return $propertyAccessor->getValue($objectOrArray, $this);
    }

    /**
     * Alias for {@link PropertyAccessor::setValue()}
     */
    public function setValue(&$objectOrArray, $value)
    {
        $propertyAccessor = PropertyAccess::getPropertyAccessor();

        return $propertyAccessor->setValue($objectOrArray, $this, $value);
    }
}
