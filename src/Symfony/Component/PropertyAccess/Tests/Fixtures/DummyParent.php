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

use Symfony\Component\PropertyAccess\Annotation\PropertyGetter;

/**
 * Fixtures for testing metadata.
 */
class DummyParent
{
    /**
     * @PropertyGetter(property="test")
     */
    public function testParent()
    {
        return 'parent';
    }
}
