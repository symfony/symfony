<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Input\File;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\File\InputFileType;
use Symfony\Component\Console\Tests\Fixtures\InputFileTypeAliasFixture;
use Symfony\Component\Console\Tests\Fixtures\InputFileTypeFixture;

class InputFileTypeTest extends TestCase
{
    /**
     * @param class-string $class
     */
    #[DataProvider('collectionProvider')]
    public function testIsInputFileCollection(string $class, string $method, bool $expected)
    {
        $this->assertSame($expected, InputFileType::isInputFileCollection($this->member($class, $method)));
    }

    public static function collectionProvider(): iterable
    {
        yield 'variadic' => [InputFileTypeFixture::class, 'variadic', true];
        yield 'short name @param InputFile[]' => [InputFileTypeFixture::class, 'arrayShortName', true];
        yield 'list<InputFile>' => [InputFileTypeFixture::class, 'listGeneric', true];
        yield 'array<int, InputFile>' => [InputFileTypeFixture::class, 'arrayGeneric', true];
        yield 'non-empty-list<InputFile>' => [InputFileTypeFixture::class, 'nonEmptyList', true];
        yield 'InputFile[]|null' => [InputFileTypeFixture::class, 'nullableArray', true];
        yield 'aliased import' => [InputFileTypeFixture::class, 'aliasedImport', true];
        yield 'iterable-typed member is not a collection' => [InputFileTypeFixture::class, 'iterableType', false];
        yield 'fully-qualified name' => [InputFileTypeAliasFixture::class, 'fullyQualified', true];
        yield 'single file is not a collection' => [InputFileTypeFixture::class, 'single', false];
        yield 'plain array without phpdoc' => [InputFileTypeFixture::class, 'plainArray', false];
        yield 'array of strings' => [InputFileTypeFixture::class, 'stringArray', false];
        yield 'short name resolving to another class' => [InputFileTypeAliasFixture::class, 'sameShortName', false];
    }

    public function testIsInputFile()
    {
        $this->assertTrue(InputFileType::isInputFile($this->member(InputFileTypeFixture::class, 'single')));
        $this->assertFalse(InputFileType::isInputFile($this->member(InputFileTypeFixture::class, 'variadic')));
        $this->assertFalse(InputFileType::isInputFile($this->member(InputFileTypeFixture::class, 'arrayShortName')));
    }

    public function testDetectsPropertyVarTag()
    {
        $member = new ReflectionMember(new \ReflectionProperty(InputFileTypeFixture::class, 'propertyArray'));

        $this->assertTrue(InputFileType::isInputFileCollection($member));
    }

    public function testDetectsPromotedProperty()
    {
        $member = new ReflectionMember(new \ReflectionProperty(InputFileTypeFixture::class, 'promoted'));

        $this->assertTrue(InputFileType::isInputFileCollection($member));
    }

    /**
     * @param class-string $class
     */
    private function member(string $class, string $method): ReflectionMember
    {
        return new ReflectionMember((new \ReflectionMethod($class, $method))->getParameters()[0]);
    }
}
