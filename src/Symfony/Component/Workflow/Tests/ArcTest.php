<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Arc;

class ArcTest extends TestCase
{
    public function testConstructorWithInvalidPlaceName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The place name cannot be empty.');

        new Arc('', 1);
    }

    public function testConstructorWithInvalidWeight()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The weight must be greater than 0, 0 given.');

        new Arc('not empty', 0);
    }

    public function testConstructorWithZeroPlaceName()
    {
        $arc = new Arc('0', 1);
        $this->assertEquals('0', $arc->place);
    }
}
