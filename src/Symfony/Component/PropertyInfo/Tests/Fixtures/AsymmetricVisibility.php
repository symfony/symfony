<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class AsymmetricVisibility
{
    public private(set) mixed $publicPrivate;
    public protected(set) mixed $publicProtected;
    protected private(set) mixed $protectedPrivate;
}
