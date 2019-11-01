<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Marshaller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\DeflateMarshaller;

/**
 * @requires extension zlib
 */
class DeflateMarshallerTest extends TestCase
{
    public function testMarshall()
    {
        $defaultMarshaller = new DefaultMarshaller();
        $deflateMarshaller = new DeflateMarshaller($defaultMarshaller);

        $values = ['abc' => [str_repeat('def', 100)]];

        $failed = [];
        $defaultResult = $defaultMarshaller->marshall($values, $failed);

        $deflateResult = $deflateMarshaller->marshall($values, $failed);
        $deflateResult['abc'] = gzinflate($deflateResult['abc']);

        $this->assertSame($defaultResult, $deflateResult);
    }

    public function testUnmarshall()
    {
        $defaultMarshaller = new DefaultMarshaller();
        $deflateMarshaller = new DeflateMarshaller($defaultMarshaller);

        $values = ['abc' => [str_repeat('def', 100)]];

        $defaultResult = $defaultMarshaller->marshall($values, $failed);
        $deflateResult = $deflateMarshaller->marshall($values, $failed);

        $this->assertSame($values['abc'], $deflateMarshaller->unmarshall($deflateResult['abc']));
        $this->assertSame($values['abc'], $deflateMarshaller->unmarshall($defaultResult['abc']));
    }
}
