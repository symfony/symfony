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
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RegexTest extends TestCase
{
    public function testConstraintGetDefaultOption()
    {
        $constraint = new Regex('/^[0-9]+$/');

        $this->assertSame('/^[0-9]+$/', $constraint->pattern);
    }

    public static function provideHtmlPatterns()
    {
        return [
            // HTML5 wraps the pattern in ^(?:pattern)$
            ['/^[0-9]+$/', '[0-9]+'],
            ['/[0-9]+$/', '.*[0-9]+'],
            ['/^[0-9]+/', '[0-9]+.*'],
            ['/[0-9]+/', '.*[0-9]+.*'],
            // We need a smart way to allow matching of patterns that contain
            // ^ and $ at various sub-clauses of an or-clause
            // .*(pattern).* seems to work correctly
            ['/[0-9]$|[a-z]+/', '.*([0-9]$|[a-z]+).*'],
            ['/[0-9]$|^[a-z]+/', '.*([0-9]$|^[a-z]+).*'],
            ['/^[0-9]|[a-z]+$/', '.*(^[0-9]|[a-z]+$).*'],
            // Unescape escaped delimiters
            ['/^[0-9]+\/$/', '[0-9]+/'],
            ['#^[0-9]+\#$#', '[0-9]+#'],
            // Cannot be converted
            ['/^[0-9]+$/i', null],

            // Inverse matches are simple, just wrap in
            // ((?!pattern).)*
            ['/^[0-9]+$/', '((?!^[0-9]+$).)*', false],
            ['/[0-9]+$/', '((?![0-9]+$).)*', false],
            ['/^[0-9]+/', '((?!^[0-9]+).)*', false],
            ['/[0-9]+/', '((?![0-9]+).)*', false],
            ['/[0-9]$|[a-z]+/', '((?![0-9]$|[a-z]+).)*', false],
            ['/[0-9]$|^[a-z]+/', '((?![0-9]$|^[a-z]+).)*', false],
            ['/^[0-9]|[a-z]+$/', '((?!^[0-9]|[a-z]+$).)*', false],
            ['/^[0-9]+\/$/', '((?!^[0-9]+/$).)*', false],
            ['#^[0-9]+\#$#', '((?!^[0-9]+#$).)*', false],
            ['/^[0-9]+$/i', null, false],
        ];
    }

    /**
     * @dataProvider provideHtmlPatterns
     */
    public function testGetHtmlPattern($pattern, $htmlPattern, $match = true)
    {
        $constraint = new Regex([
            'pattern' => $pattern,
            'match' => $match,
        ]);

        $this->assertSame($pattern, $constraint->pattern);
        $this->assertSame($htmlPattern, $constraint->getHtmlPattern());
    }

    public function testGetCustomHtmlPattern()
    {
        $constraint = new Regex([
            'pattern' => '((?![0-9]$|[a-z]+).)*',
            'htmlPattern' => 'foobar',
        ]);

        $this->assertSame('((?![0-9]$|[a-z]+).)*', $constraint->pattern);
        $this->assertSame('foobar', $constraint->getHtmlPattern());
    }

    public function testNormalizerCanBeSet()
    {
        $regex = new Regex(['pattern' => '/^[0-9]+$/', 'normalizer' => 'trim']);

        $this->assertEquals('trim', $regex->normalizer);
    }

    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new Regex(['pattern' => '/^[0-9]+$/', 'normalizer' => 'Unknown Callable']);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new Regex(['pattern' => '/^[0-9]+$/', 'normalizer' => new \stdClass()]);
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(RegexDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame('/^[0-9]+$/', $aConstraint->pattern);
        self::assertTrue($aConstraint->match);
        self::assertNull($aConstraint->normalizer);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame('/^[0-9]+$/', $bConstraint->pattern);
        self::assertSame('[0-9]+', $bConstraint->htmlPattern);
        self::assertFalse($bConstraint->match);
        self::assertSame(['Default', 'RegexDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class RegexDummy
{
    #[Regex('/^[0-9]+$/')]
    private $a;

    #[Regex(message: 'myMessage', pattern: '/^[0-9]+$/', htmlPattern: '[0-9]+', match: false, normalizer: 'trim')]
    private $b;

    #[Regex('/^[0-9]+$/', groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
