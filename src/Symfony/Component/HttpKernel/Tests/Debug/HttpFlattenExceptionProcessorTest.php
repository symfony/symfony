<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Debug;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpKernel\Debug\HttpFlattenExceptionProcessor;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class HttpFlattenExceptionProcessorTest extends \PHPUnit_Framework_TestCase
{
    private $processor;

    protected function setUp()
    {
        $this->processor = new HttpFlattenExceptionProcessor();
    }

    public function getStatusCode()
    {
        return array(
            array(400, new BadRequestHttpException()),
            array(401, new UnauthorizedHttpException('Basic realm="My Realm"')),
            array(403, new AccessDeniedHttpException()),
            array(404, new NotFoundHttpException()),
            array(405, new MethodNotAllowedHttpException(array('POST'))),
            array(406, new NotAcceptableHttpException()),
            array(409, new ConflictHttpException()),
            array(410, new GoneHttpException()),
            array(411, new LengthRequiredHttpException()),
            array(412, new PreconditionFailedHttpException()),
            array(415, new UnsupportedMediaTypeHttpException()),
            array(428, new PreconditionRequiredHttpException()),
            array(429, new TooManyRequestsHttpException()),
            array(500, new \RuntimeException()),
            array(503, new ServiceUnavailableHttpException()),
        );
    }

    /**
     * @dataProvider getStatusCode
     */
    public function testStatusCode($code, $exception)
    {
        $flattened = new FlattenException();
        $this->processor->process($exception, $flattened, true);
        $this->assertEquals($code, $flattened->getStatusCode());
    }

    public function testCustomStatusCode()
    {
        $flattened = new FlattenException();
        $flattened->setStatusCode(403);
        $this->processor->process(new \RuntimeException(), $flattened, true);
        $this->assertEquals(403, $flattened->getStatusCode());
    }

    public function getHeaders()
    {
        return array(
            array(array('Allow' => 'POST'), new MethodNotAllowedHttpException(array('POST'))),
            array(array('WWW-Authenticate' => 'Basic realm="My Realm"'), new UnauthorizedHttpException('Basic realm="My Realm"')),
            array(array('Retry-After' => 'Fri, 31 Dec 1999 23:59:59 GMT'), new ServiceUnavailableHttpException('Fri, 31 Dec 1999 23:59:59 GMT')),
            array(array('Retry-After' => 120), new ServiceUnavailableHttpException(120)),
            array(array('Retry-After' => 'Fri, 31 Dec 1999 23:59:59 GMT'), new TooManyRequestsHttpException('Fri, 31 Dec 1999 23:59:59 GMT')),
            array(array('Retry-After' => 120), new TooManyRequestsHttpException(120)),
        );
    }

    /**
     * @dataProvider getHeaders
     */
    public function testHeadersForHttpException($headers, $exception)
    {
        $flattened = new FlattenException();
        $this->processor->process($exception, $flattened, true);
        $this->assertEquals($headers, $flattened->getHeaders());
    }
}
