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

        $this->assertEquals($exception->getMessage(), $flattened->getMessage(), 'The message is copied from the original exception.');
        $this->assertEquals($exception->getCode(), $flattened->getCode(), 'The code is copied from the original exception.');
        $this->assertEquals(get_class($exception), $flattened->getClass(), 'The class is set to the class of the original exception');

    }

    public function flattenDataProvider()
    {
        return array(
            array(new \Exception('test', 123), 500),
        );
    }
}
