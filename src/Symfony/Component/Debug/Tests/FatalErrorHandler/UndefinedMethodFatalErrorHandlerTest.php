<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Tests\FatalErrorHandler;

use Symfony\Component\Debug\Exception\FatalErrorException;
use Symfony\Component\Debug\FatalErrorHandler\UndefinedMethodFatalErrorHandler;

class UndefinedMethodFatalErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideUndefinedMethodData
     */
    public function testUndefinedMethod($error, $translatedMessage)
    {
        $handler = new UndefinedMethodFatalErrorHandler();
        $exception = $handler->handleError($error, new FatalErrorException('', 0, $error['type'], $error['file'], $error['line']));

        $this->assertInstanceof('Symfony\Component\Debug\Exception\UndefinedMethodException', $exception);
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
                'Attempted to call method "what" on class "SplObjectStorage".',
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Call to undefined method SplObjectStorage::walid()',
                ),
                "Attempted to call method \"walid\" on class \"SplObjectStorage\".\nDid you mean to call \"valid\"?",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Call to undefined method SplObjectStorage::offsetFet()',
                ),
                "Attempted to call method \"offsetFet\" on class \"SplObjectStorage\".\nDid you mean to call e.g. \"offsetGet\", \"offsetSet\" or \"offsetUnset\"?",
            ),
        );
    }
}
