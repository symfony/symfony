<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\ErrorEnhancer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\Error\UndefinedMethodError;
use Symfony\Component\ErrorHandler\ErrorEnhancer\UndefinedMethodErrorEnhancer;

class UndefinedMethodErrorEnhancerTest extends TestCase
{
    /**
     * @dataProvider provideUndefinedMethodData
     */
    public function testEnhance(string $originalMessage, string $enhancedMessage)
    {
        $enhancer = new UndefinedMethodErrorEnhancer();

        $expectedLine = __LINE__ + 1;
        $error = $enhancer->enhance(new \Error($originalMessage));

        $this->assertInstanceOf(UndefinedMethodError::class, $error);
        $this->assertSame($enhancedMessage, $error->getMessage());
        $this->assertSame(realpath(__FILE__), $error->getFile());
        $this->assertSame($expectedLine, $error->getLine());
    }

    public static function provideUndefinedMethodData()
    {
        return [
            [
                'Call to undefined method SplObjectStorage::what()',
                'Attempted to call an undefined method named "what" of class "SplObjectStorage".',
            ],
            [
                'Call to undefined method SplObjectStorage::()',
                'Attempted to call an undefined method named "" of class "SplObjectStorage".',
            ],
            [
                'Call to undefined method SplObjectStorage::walid()',
                "Attempted to call an undefined method named \"walid\" of class \"SplObjectStorage\".\nDid you mean to call \"valid\"?",
            ],
            [
                'Call to undefined method SplObjectStorage::offsetFet()',
                "Attempted to call an undefined method named \"offsetFet\" of class \"SplObjectStorage\".\nDid you mean to call e.g. \"offsetGet\", \"offsetSet\" or \"offsetUnset\"?",
            ],
            [
                'Call to undefined method class@anonymous::test()',
                'Attempted to call an undefined method named "test" of class "class@anonymous".',
            ],
        ];
    }
}
