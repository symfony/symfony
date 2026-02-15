<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Question;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Question\FileQuestion;
use Symfony\Component\Validator\Constraints\File;

class FileQuestionTest extends TestCase
{
    public function testGetQuestion()
    {
        $question = new FileQuestion('Provide a file:');

        $this->assertSame('Provide a file:', $question->getQuestion());
    }

    public function testDefaultOptions()
    {
        $question = new FileQuestion('Provide a file:');

        $this->assertTrue($question->isPasteAllowed());
        $this->assertTrue($question->isPathAllowed());
        $this->assertFalse($question->isTrimmable());
    }

    public function testWithAllowPasteFalse()
    {
        $question = new FileQuestion('Provide a file:', false);

        $this->assertFalse($question->isPasteAllowed());
    }

    public function testWithAllowPathFalse()
    {
        $question = new FileQuestion('Provide a file:', true, false);

        $this->assertFalse($question->isPathAllowed());
    }

    public function testBothPasteAndPathFalseThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one of allowPaste or allowPath must be true.');

        new FileQuestion('Provide a file:', false, false);
    }

    public function testIsNotTrimmableByDefault()
    {
        $question = new FileQuestion('Provide a file:');

        $this->assertFalse($question->isTrimmable());
    }

    public function testSupportsConstraints()
    {
        if (!class_exists(File::class)) {
            $this->markTestSkipped('Validator component not available.');
        }

        $question = new FileQuestion('Provide a file:');
        $constraint = new File(maxSize: '5M', mimeTypes: ['image/png']);

        $question->setConstraints([$constraint]);

        $this->assertSame([$constraint], $question->getConstraints());
    }
}
