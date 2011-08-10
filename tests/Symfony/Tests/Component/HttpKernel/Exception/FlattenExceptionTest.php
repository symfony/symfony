<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
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
        $flattened2 = FlattenException::create($exception);

        $flattened->setPrevious($flattened2);

        $this->assertEquals($exception->getMessage(), $flattened->getMessage(), 'The message is copied from the original exception.');
        $this->assertEquals($exception->getCode(), $flattened->getCode(), 'The code is copied from the original exception.');
        $this->assertEquals(get_class($exception), $flattened->getClass(), 'The class is set to the class of the original exception');

    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testPrevious(\Exception $exception, $statusCode)
    {
        $flattened = FlattenException::create($exception);
        $flattened2 = FlattenException::create($exception);

        $flattened->setPrevious($flattened2);

        $this->assertSame($flattened2,$flattened->getPrevious());

        $this->assertSame(array($flattened2),$flattened->getAllPrevious());
    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testToArray(\Exception $exception, $statusCode)
    {
        $flattened = FlattenException::create($exception);
        $flattened->setTrace(array(),'foo.php',123);

        $this->assertEquals(array(
            array(
                'message'=> 'test',
                'class'=>'Exception',
                'trace'=>array(array(
                    'namespace'   => '', 'short_class' => '', 'class' => '','type' => '','function' => '', 'file' => 'foo.php','line' => 123,
                    'args'        => array()
                )),
            )
        ),$flattened->toArray());
    }

    public function flattenDataProvider()
    {
        return array(
            array(new \Exception('test', 123), 500),
        );
    }

    public function testRecursionInArguments()
    {
        $a = array('foo', array(2, &$a));
        $exception = $this->createException($a);

        $flattened = FlattenException::create($exception);
        $trace = $flattened->getTrace();
        $this->assertContains('*DEEP NESTED ARRAY*', serialize($trace));
    }

    private function createException($foo)
    {
        return new \Exception();
    }
}
