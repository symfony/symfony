<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Form\Extension\Core\DataTransformer\PhoneNumberToStringTransformer;

class PhoneNumberToStringTransformerTest extends \PHPUnit_Framework_TestCase
{
    public function testTransform()
    {
        $transformer = new PhoneNumberToStringTransformer("FR");

        $this->assertEquals("+33256789876", $transformer->transform(PhoneNumberUtil::getInstance()->parse("+33256789876", null)));
    }

    public function testReverseTransform()
    {
        $transformer = new PhoneNumberToStringTransformer("FR");

        $this->assertInstanceOf("libphonenumber\PhoneNumber", $transformer->reverseTransform('0645656567'));
    }

}
