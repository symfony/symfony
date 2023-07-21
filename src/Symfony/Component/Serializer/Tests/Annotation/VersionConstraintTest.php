<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Annotation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\VersionConstraint;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Olivier Michaud <olivier@micoli.org>
 */
class VersionConstraintTest extends TestCase
{
    /**
     * @dataProvider providesNormalizeVersionAndConstraints
     */
    public function testVersionConstraintLogic(bool $expectedResult, ?string $version, ?string $since, ?string $until)
    {
        $versionConstraint = new VersionConstraint(since: $since, until: $until);
        $this->assertSame($expectedResult, $versionConstraint->isVersionCompatible($version));
    }

    public static function providesNormalizeVersionAndConstraints(): \Generator
    {
        yield 'Version in range' => [true, '1.2', '1.1', '1.5'];
        yield 'Version below range with both limits' => [false, '0.9', '1.1', '1.5'];
        yield 'Version below range only with lower limit' => [false, '0.9', '1.1', null];
        yield 'Version in range only with upper limit' => [true, '0.9', null, '1.5'];
        yield 'Version above range with both limits' => [false, '2.0', '1.1', '1.5'];
        yield 'Version above range only with upper limit' => [false, '2.0', null, '1.5'];
        yield 'Version in range only with low limit ' => [true, '2.0', '1.1', null];
        yield 'No version to no limits' => [false, '', '1.1', '1.5'];
    }

    public function testSinceParameterNotEmptyString()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter "since" of annotation "Symfony\Component\Serializer\Annotation\VersionConstraint" must be a non-empty string.');
        new VersionConstraint(since: '', until: '2.0');
    }

    public function testUntilParameterNotEmptyString()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter "until" of annotation "Symfony\Component\Serializer\Annotation\VersionConstraint" must be a non-empty string.');
        new VersionConstraint(since: '1.1', until: '');
    }

    public function testBothSinceAndUntilParameterAreNull()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one of "since" or "until" properties of annotation "Symfony\Component\Serializer\Annotation\VersionConstraint" have to be defined.');
        new VersionConstraint(since: null, until: null);
    }

}
