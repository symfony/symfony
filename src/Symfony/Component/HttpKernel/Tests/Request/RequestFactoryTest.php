<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Factory\RequestFactory;

class RequestFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateRequest()
    {
        $factory = new RequestFactory();

        $this->assertEquals(
            Request::create('uri', 'method', array('parameter' => 'value'), array('cookie' => 'value'), array(), array('server' => 'value'), 'content'),
            $factory->create('uri', 'method', array('parameter' => 'value'), array('cookie' => 'value'), array(), array('server' => 'value'), 'content')
        );
    }
}
