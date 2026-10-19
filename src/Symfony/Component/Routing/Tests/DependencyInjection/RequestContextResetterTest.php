<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\DependencyInjection\RequestContextResetter;
use Symfony\Component\Routing\RequestContext;

class RequestContextResetterTest extends TestCase
{
    public function testResetRestoresTheInitialState()
    {
        $context = new RequestContext('/app', 'GET', 'example.com', 'https', 8080, 8443, '/', 'a=1', ['_locale' => 'en']);
        $initialContext = clone $context;

        $resetter = new RequestContextResetter();
        $resetter($context);

        $context->fromRequest(Request::create('http://localhost:81/index.php/foo?b=2', 'POST'));
        $context->setParameter('_locale', 'fr');
        $context->setParameter('foo', 'bar');

        $resetter->reset();

        $this->assertEquals($initialContext, $context);
    }
}
