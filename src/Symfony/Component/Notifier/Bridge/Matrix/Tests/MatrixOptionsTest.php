<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Matrix\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Matrix\MatrixOptions;

class MatrixOptionsTest extends TestCase
{
    public function testToArray()
    {
        $options = new MatrixOptions([
            'recipient_id' => '@testuser:matrix.io',
            'msgtype' => 'm.text',
            'format' => 'org.matrix.custom.html',
        ]);
        $this->assertSame(['recipient_id' => '@testuser:matrix.io', 'msgtype' => 'm.text', 'format' => 'org.matrix.custom.html'], $options->toArray());
    }

    public function testGetRecipientId()
    {
        $options = new MatrixOptions([
            'recipient_id' => '@testuser:matrix.io',
        ]);
        $this->assertSame('@testuser:matrix.io', $options->getRecipientId());
    }
}
