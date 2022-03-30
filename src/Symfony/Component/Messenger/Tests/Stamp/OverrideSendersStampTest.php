<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\ViaSenderStamp;

class ViaSenderStampTest extends TestCase
{
    public function testGetSenders()
    {
        $configuredSenders = ['first_transport', 'second_transport', 'other_transport'];
        $stamp = new ViaSenderStamp($configuredSenders);
        $stampSenders = $stamp->getSenders();
        $this->assertEquals(\count($configuredSenders), \count($stampSenders));

        foreach ($configuredSenders as $key => $sender) {
            $this->assertSame($sender, $stampSenders[$key]);
        }
    }
}
