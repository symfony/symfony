<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use Symfony\Component\Console\Tests\Fixtures\Helper\SomeInputAwareHelper;

class InputAwareHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testStrlen()
    {
        $helper = new SomeInputAwareHelper();

        $inputMock = $this->prophesize('Symfony\Component\Console\Input\InputInterface');
        $helper->setInput($inputMock->reveal());

        $this->assertInstanceOf('Symfony\Component\Console\Input\InputInterface', \PHPUnit_Framework_Assert::getObjectAttribute($helper, 'input'));
    }
}
