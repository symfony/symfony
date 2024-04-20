<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPStan\PhpDocParser\Parser\PhpDocParser;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\TypeInfo\Dummy;
use Symfony\Component\TypeInfo\Type;

class TypeInfoTest extends AbstractWebTestCase
{
    public function testComponent()
    {
        static::bootKernel(['test_case' => 'TypeInfo']);

        $this->assertEquals(Type::string(), static::getContainer()->get('type_info.resolver')->resolve(new \ReflectionProperty(Dummy::class, 'name')));

        if (!class_exists(PhpDocParser::class)) {
            $this->markTestSkipped('"phpstan/phpdoc-parser" dependency is required.');
        }

        $this->assertEquals(Type::int(), static::getContainer()->get('type_info.resolver')->resolve('int'));
    }
}
