<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataMapper;

use Symfony\Component\Form\Extension\Core\DataMapper\CallbackFormDataToObjectConverter;

class CallbackFormDataToObjectConverterTest extends \PHPUnit_Framework_TestCase
{
    public function testConvertFormDataToObject()
    {
        $data = array('amount' => 15.0, 'currency' => 'EUR');
        $originalData = (object) $data;

        $converter = new CallbackFormDataToObjectConverter(function ($arg1, $arg2) use ($data, $originalData) {
            $this->assertSame($data, $arg1);
            $this->assertSame($originalData, $arg2);

            return 'converted';
        });

        $this->assertSame('converted', $converter->convertFormDataToObject($data, $originalData));
    }
}
