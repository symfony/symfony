<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\Deprecation;

class DeprecationTest extends TestCase
{
    public function testItCanDetermineTheClassWhereTheDeprecationHappened()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertTrue($deprecation->originatesFromAnObject());
        $this->assertSame(self::class, $deprecation->originatingClass());
        $this->assertSame(__FUNCTION__, $deprecation->originatingMethod());
    }

    public function testItCanTellWhetherItIsInternal()
    {
        $r = new \ReflectionClass(Deprecation::class);

        if (dirname($r->getFileName(), 2) !== dirname(__DIR__, 2)) {
            $this->markTestSkipped('Test case is not compatible with having the bridge in vendor/');
        }

        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertSame(Deprecation::TYPE_SELF, $deprecation->getType());
    }

    public function testLegacyTestMethodIsDetectedAsSuch()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertTrue($deprecation->isLegacy('whatever'));
    }

    public function testItCanBeConvertedToAString()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertContains('💩', $deprecation->toString());
        $this->assertContains(__FUNCTION__, $deprecation->toString());
    }

    public function testItRulesOutFilesOutsideVendorsAsIndirect()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertNotSame(Deprecation::TYPE_INDIRECT, $deprecation->getType());
    }

    /**
     * @dataProvider mutedProvider
     */
    public function testItMutesOnlySpecificErrorMessagesWhenTheCallingCodeIsInPhpunit($muted, $callingClass, $message)
    {
        $trace = $this->debugBacktrace();
        array_unshift($trace, ['class' => $callingClass]);
        array_unshift($trace, ['class' => DeprecationErrorHandler::class]);
        $deprecation = new Deprecation($message, $trace, 'should_not_matter.php');
        $this->assertSame($muted, $deprecation->isMuted());
    }

    public function mutedProvider()
    {
        yield 'not from phpunit, and not a whitelisted message' => [
            false,
            \My\Source\Code::class,
            'Self deprecating humor is deprecated by itself'
        ];
        yield 'from phpunit, but not a whitelisted message' => [
            false,
            \PHPUnit\Random\Piece\Of\Code::class,
            'Self deprecating humor is deprecated by itself'
        ];
        yield 'whitelisted message, but not from phpunit' => [
            false,
            \My\Source\Code::class,
            'Function ReflectionType::__toString() is deprecated',
        ];
        yield 'from phpunit and whitelisted message' => [
            true,
            \PHPUnit\Random\Piece\Of\Code::class,
            'Function ReflectionType::__toString() is deprecated',
        ];
    }

    public function testNotMutedIfNotCalledFromAClassButARandomFile()
    {
        $deprecation = new Deprecation(
            'Function ReflectionType::__toString() is deprecated',
            [
                ['file' => 'should_not_matter.php'],
                ['file' => 'should_not_matter_either.php'],
            ],
            'my-procedural-controller.php'
        );
        $this->assertFalse($deprecation->isMuted());
    }

    public function testItTakesMutesDeprecationFromPhpUnitFiles()
    {
        $deprecation = new Deprecation(
            'Function ReflectionType::__toString() is deprecated',
            [
                ['file' => 'should_not_matter.php'],
                ['file' => 'should_not_matter_either.php'],
            ],
            'random_path' . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'phpunit' . \DIRECTORY_SEPARATOR . 'whatever.php'
        );
        $this->assertTrue($deprecation->isMuted());
    }

    /**
     * This method is here to simulate the extra level from the piece of code
     * triggering an error to the error handler
     */
    public function debugBacktrace(): array
    {
        return debug_backtrace();
    }
}
