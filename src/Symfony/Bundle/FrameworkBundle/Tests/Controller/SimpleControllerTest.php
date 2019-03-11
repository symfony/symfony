<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\SimpleController;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class SimpleControllerTest extends TestCase
{
    public function testSimpleResponse()
    {
        $response = (new SimpleController())('content', 203, ['Content-Type' => 'text/plain'], 10, 20);
        $this->assertSame('content', $response->getContent());
        $this->assertSame(203, $response->getStatusCode());
        $this->assertSame('text/plain', $response->headers->get('Content-Type'));
        $this->assertSame('max-age=10, public, s-maxage=20', $response->headers->get('Cache-Control'));
    }

    public function testPrivateResponse()
    {
        $response = (new SimpleController())('', 200, [], null, null, true);
        $this->assertSame('private', $response->headers->get('Cache-Control'));
    }
}
