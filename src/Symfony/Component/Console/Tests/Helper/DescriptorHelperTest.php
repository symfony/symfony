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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\DescriptorHelper;

class DescriptorHelperTest extends TestCase
{
    public function testGetFormats()
    {
        $helper = new DescriptorHelper();
        $expectedFormats = [
            'txt',
            'xml',
            'json',
            'md',
            'rst',
        ];
        $this->assertSame($expectedFormats, $helper->getFormats());
    }
}
