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
use Symfony\Component\Validator\Constraints\WordCount;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

/**
 * @requires extension intl
 */
class WordCountTest extends TestCase
{
    public function testLocaleIsSet()
    {
        $wordCount = new WordCount(min: 1, locale: 'en');

        $this->assertSame('en', $wordCount->locale);
    }

    public function testOnlyMinIsSet()
    {
        $wordCount = new WordCount(1);

        $this->assertSame(1, $wordCount->min);
        $this->assertNull($wordCount->max);
        $this->assertNull($wordCount->locale);
    }

    public function testOnlyMaxIsSet()
    {
        $wordCount = new WordCount(max: 1);

        $this->assertNull($wordCount->min);
        $this->assertSame(1, $wordCount->max);
        $this->assertNull($wordCount->locale);
    }

    public function testMinIsNegative()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\WordCount" constraint requires the min word count to be a positive integer if set.');

        new WordCount(-1);
    }

    public function testMinIsZero()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\WordCount" constraint requires the min word count to be a positive integer if set.');

        new WordCount(0);
    }

    public function testMaxIsNegative()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\WordCount" constraint requires the max word count to be a positive integer if set.');

        new WordCount(max: -1);
    }

    public function testMaxIsZero()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\WordCount" constraint requires the max word count to be a positive integer if set.');

        new WordCount(max: 0);
    }

    public function testNothingIsSet()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('Either option "min" or "max" must be given for constraint "Symfony\Component\Validator\Constraints\WordCount".');

        new WordCount();
    }

    public function testMaxIsLessThanMin()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\WordCount" constraint requires the min word count to be less than or equal to the max word count.');

        new WordCount(2, 1);
    }

    public function testMinAndMaxAreEquals()
    {
        $wordCount = new WordCount(1, 1);

        $this->assertSame(1, $wordCount->min);
        $this->assertSame(1, $wordCount->max);
        $this->assertNull($wordCount->locale);
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(WordCountDummy::class);
        $loader = new AttributeLoader();
        $this->assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        $this->assertSame(1, $aConstraint->min);
        $this->assertNull($aConstraint->max);
        $this->assertNull($aConstraint->locale);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        $this->assertSame(2, $bConstraint->min);
        $this->assertSame(5, $bConstraint->max);
        $this->assertNull($bConstraint->locale);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        $this->assertSame(3, $cConstraint->min);
        $this->assertNull($cConstraint->max);
        $this->assertSame('en', $cConstraint->locale);
    }
}

class WordCountDummy
{
    #[WordCount(min: 1)]
    private string $a;

    #[WordCount(min: 2, max: 5)]
    private string $b;

    #[WordCount(min: 3, locale: 'en')]
    private string $c;
}
