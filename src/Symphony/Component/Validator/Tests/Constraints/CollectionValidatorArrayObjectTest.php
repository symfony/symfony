<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Constraints;

class CollectionValidatorArrayObjectTest extends CollectionValidatorTest
{
    public function prepareTestData(array $contents)
    {
        return new \ArrayObject($contents);
    }
}
