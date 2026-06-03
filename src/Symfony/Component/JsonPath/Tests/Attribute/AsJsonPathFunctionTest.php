<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonPath\Attribute\AsJsonPathFunction;

class AsJsonPathFunctionTest extends TestCase
{
    public function testAttributeStoresFunctionName()
    {
        $attribute = new AsJsonPathFunction('upper');

        $this->assertSame('upper', $attribute->name);
    }

    public function testAttributeTargetsClassesOnly()
    {
        $reflection = new \ReflectionClass(AsJsonPathFunction::class);
        $attribute = $reflection->getAttributes(\Attribute::class)[0]->newInstance();

        $this->assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
    }
}
