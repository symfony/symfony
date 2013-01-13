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

use Symfony\Component\PropertyAccess\PropertyPathInterface as BasePropertyPathInterface;

/**
 * Alias for {@link \Symfony\Component\PropertyAccess\PropertyPathInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated deprecated since version 2.2, to be removed in 2.3. Use
 *             {@link \Symfony\Component\PropertyAccess\PropertyPathInterface}
 *             instead.
 */
interface PropertyPathInterface extends BasePropertyPathInterface
{
}
