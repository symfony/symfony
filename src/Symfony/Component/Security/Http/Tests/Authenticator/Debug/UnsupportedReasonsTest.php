<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Authenticator\Debug\UnsupportedReasons;

class UnsupportedReasonsTest extends TestCase
{
    public function testEmptyByDefault()
    {
        $this->assertSame([], (new UnsupportedReasons())->all());
    }

    public function testAddKeepsTheOrder()
    {
        $reasons = new UnsupportedReasons();
        $reasons->add('the request is not a POST');
        $reasons->add('the request has no "Content-Type" header');

        $this->assertSame(['the request is not a POST', 'the request has no "Content-Type" header'], $reasons->all());
    }
}
