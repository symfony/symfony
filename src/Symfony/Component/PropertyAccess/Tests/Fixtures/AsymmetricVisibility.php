<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

class AsymmetricVisibility
{
    public public(set) mixed $publicPublic = null;
    public protected(set) mixed $publicProtected = null;
    public private(set) mixed $publicPrivate = null;
    private private(set) mixed $privatePrivate = null;
    public bool $virtualNoSetHook { get => true; }
}
