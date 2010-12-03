<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\Exception;
use Symfony\Component\HttpKernel\Exception\FlattenException;

class FlattenExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider flattenDataProvider
     */
    public function testFlattenHttpException(\Exception $exception, $statusCode)
    {
        $flattened = FlattenException::create($exception);

        $this->assertEquals($statusCode, $flattened->getStatusCode(), 'A HttpKernel exception uses the error code as the status code.');
        $this->assertEquals($exception->getMessage(), $flattened->getMessage(), 'The message is copied from the original exception.');
        $this->assertEquals($exception->getCode(), $flattened->getCode(), 'The code is copied from the original exception.');
        $this->assertEquals(get_class($exception), $flattened->getClass(), 'The class is set to the class of the original exception');

    }

    public function flattenDataProvider()
    {
        return array(
            array(new TestHttpException('test', 404), 404),
            array(new \Exception('test', 123), 500),
        );
    }
}

use Symfony\Component\HttpKernel\Exception\HttpException;

// stub Exception class that extends HttpException
class TestHttpException extends HttpException
{
}