<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mercure\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Mercure\MercureOptions;

final class MercureOptionsTest extends TestCase
{
    public function testConstructWithDefaults()
    {
        $this->assertSame((new MercureOptions())->toArray(), [
            'topics' => null,
            'private' => false,
            'id' => null,
            'type' => null,
            'retry' => null,
        ]);
    }

    public function testConstructWithParameters()
    {
        $options = (new MercureOptions('/topic/1', true, 'id', 'type', 1));

        $this->assertSame($options->toArray(), [
            'topics' => ['/topic/1'],
            'private' => true,
            'id' => 'id',
            'type' => 'type',
            'retry' => 1,
        ]);
    }

    public function testConstructWithWrongTopicsThrows()
    {
        $this->expectException(\TypeError::class);
        new MercureOptions(new \stdClass());
    }
}
