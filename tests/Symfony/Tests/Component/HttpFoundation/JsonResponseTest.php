<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * JsonResponseTest
 *
 * @author Christian Hoffmeister <choffmeister.github@googlemail.com>
 */
class ServerBagTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $data = array(
            'first' => 1,
            'second' => '2nd',
            'third' => array(
                3 => '3a',
                'four' => 4,
            ),
        );

        $jsonResponse1 = new JsonResponse($data);

        $this->assertEquals('{"first":1,"second":"2nd","third":{"3":"3a","four":4}}', $jsonResponse1->getContent());
        $this->assertEquals(200, $jsonResponse1->getStatusCode());
    }
}
