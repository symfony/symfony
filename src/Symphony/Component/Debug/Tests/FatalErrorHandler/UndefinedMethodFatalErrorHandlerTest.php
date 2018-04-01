<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Debug\Tests\FatalErrorHandler;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Debug\Exception\FatalErrorException;
use Symphony\Component\Debug\FatalErrorHandler\UndefinedMethodFatalErrorHandler;

class UndefinedMethodFatalErrorHandlerTest extends TestCase
{
    /**
     * @dataProvider provideUndefinedMethodData
     */
    public function testUndefinedMethod($error, $translatedMessage)
    {
        $handler = new UndefinedMethodFatalErrorHandler();
        $exception = $handler->handleError($error, new FatalErrorException('', 0, $error['type'], $error['file'], $error['line']));

        $this->assertInstanceOf('Symphony\Component\Debug\Exception\UndefinedMethodException', $exception);
        $this->assertSame($translatedMessage, $exception->getMessage());
        $this->assertSame($error['type'], $exception->getSeverity());
        $this->assertSame($error['file'], $exception->getFile());
        $this->assertSame($error['line'], $exception->getLine());
    }

    public function provideUndefinedMethodData()
    {
        return array(
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Call to undefined method SplObjectStorage::what()',
                ),
                'Attempted to call an undefined method named "what" of class "SplObjectStorage".',
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Call to undefined method SplObjectStorage::walid()',
                ),
                "Attempted to call an undefined method named \"walid\" of class \"SplObjectStorage\".\nDid you mean to call \"valid\"?",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Call to undefined method SplObjectStorage::offsetFet()',
                ),
                "Attempted to call an undefined method named \"offsetFet\" of class \"SplObjectStorage\".\nDid you mean to call e.g. \"offsetGet\", \"offsetSet\" or \"offsetUnset\"?",
            ),
            array(
                array(
                  'type' => 1,
                  'message' => 'Call to undefined method class@anonymous::test()',
                  'file' => '/home/possum/work/symphony/test.php',
                  'line' => 11,
                ),
                'Attempted to call an undefined method named "test" of class "class@anonymous".',
            ),
        );
    }
}
