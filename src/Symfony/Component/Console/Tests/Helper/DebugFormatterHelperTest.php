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

use Symfony\Component\Console\Helper\DebugFormatterHelper;

class DebugFormatterHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testProgressMessage()
    {
        $helper = new DebugFormatterHelper();
        $helper->start('id', 'message');

        $this->assertSame(
            '<bg=black> </><bg=red;fg=white> ERR </> buffer',
            $helper->progress('id', 'buffer', true)
        );
    }
}
