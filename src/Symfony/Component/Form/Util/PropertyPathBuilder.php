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

use Symfony\Component\PropertyAccess\PropertyPathBuilder as BasePropertyPathBuilder;

/**
 * Alias for {@link \Symfony\Component\PropertyAccess\PropertyPathBuilder}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated deprecated since version 2.2, to be removed in 2.3. Use
 *             {@link \Symfony\Component\PropertyAccess\PropertyPathBuilder}
 *             instead.
 */
class PropertyPathBuilder extends BasePropertyPathBuilder
{
    /**
     * {@inheritdoc}
     */
    public function __construct($propertyPath)
    {
        parent::__construct($propertyPath);

        trigger_error('\Symfony\Component\Form\Util\PropertyPathBuilder is deprecated since version 2.2 and will be removed in 2.3. Use \Symfony\Component\PropertyAccess\PropertyPathBuilder instead.', E_USER_DEPRECATED);
    }
}
