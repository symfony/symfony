<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\NoBannedWords;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

class NoBannedWordsTest extends TestCase
{
    public function testConstructor()
    {
        $constraint = new NoBannedWords();
        $this->assertEquals([], $constraint->dictionary);
    }

    public function testConstructorWithParameters()
    {
        $constraint = new NoBannedWords([
            'dictionary' => ['foo', 'bar'],
        ]);

        $this->assertEquals(['foo', 'bar'], $constraint->dictionary);
    }

    public function testInvalidDictionary()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The parameter "dictionary" of the "Symfony\Component\Validator\Constraints\NoBannedWords" constraint must be a list of strings.');
        new NoBannedWords(['dictionary' => [123]]);
    }
}
