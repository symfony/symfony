<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\IdentityMarshaller;

/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class IdentityMarshallerTest extends Testcase
{
    public function testMarshall()
    {
        $marshaller = new IdentityMarshaller();
        $values = ['data' => 'string_data'];
        $failed = [];

        $this->assertSame($values, $marshaller->marshall($values, $failed));
    }

    /**
     * @dataProvider invalidMarshallDataProvider
     */
    public function testMarshallInvalidData($values)
    {
        $marshaller = new IdentityMarshaller();
        $failed = [];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Symfony\Component\HttpFoundation\Session\Storage\Handler\IdentityMarshaller::marshall accepts only string as data');

        $marshaller->marshall($values, $failed);
    }

    public function testUnmarshall()
    {
        $marshaller = new IdentityMarshaller();

        $this->assertEquals('data', $marshaller->unmarshall('data'));
    }

    public static function invalidMarshallDataProvider(): iterable
    {
        return [
            [['object' => new \stdClass()]],
            [['foo' => ['bar']]],
        ];
    }
}
