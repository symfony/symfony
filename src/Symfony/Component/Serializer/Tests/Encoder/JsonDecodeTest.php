<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Encoder;

use Symfony\Component\Serializer\Encoder\JsonDecode;

class JsonDecodeTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Symfony\Component\Serializer\Encoder\JsonDecode */
    private $decoder;

    protected function setUp()
    {
        $this->decoder = new JsonDecode(true);
    }

    public function testDecodeWithValidData()
    {
        $json = json_encode(array(
            'hello' => 'world',
        ));
        $result = $this->decoder->decode($json, 'json');
        $this->assertEquals(array(
            'hello' => 'world',
        ), $result);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDecodeWithInvalidData()
    {
        $result = $this->decoder->decode('kaboom!', 'json');
    }
}
