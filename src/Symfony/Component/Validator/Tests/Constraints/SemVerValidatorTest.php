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

use Symfony\Component\Validator\Constraints\SemVer;
use Symfony\Component\Validator\Constraints\SemVerValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class SemVerValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): SemVerValidator
    {
        return new SemVerValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new SemVer());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new SemVer());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLooseSemVersions
     */
    public function testValidLooseSemVersions(string $version)
    {
        $this->validator->validate($version, new SemVer());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidStrictSemVersions
     */
    public function testValidStrictSemVersions(string $version)
    {
        $this->validator->validate($version, new SemVer(strict: true));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidSemVersions
     */
    public function testInvalidSemVersions(string $version)
    {
        $constraint = new SemVer(message: 'myMessage');

        $this->validator->validate($version, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$version.'"')
            ->setCode(SemVer::INVALID_SEMVER_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getInvalidStrictSemVersions
     */
    public function testInvalidStrictSemVersions(string $version)
    {
        $constraint = new SemVer(strict: true, message: 'myMessage');

        $this->validator->validate($version, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$version.'"')
            ->setCode(SemVer::INVALID_SEMVER_ERROR)
            ->assertRaised();
    }

    public static function getValidLooseSemVersions(): iterable
    {
        // Full versions
        yield ['0.0.0'];
        yield ['1.0.0'];
        yield ['1.2.3'];
        yield ['10.20.30'];

        // Partial versions
        yield ['1'];
        yield ['1.2'];
        yield ['10.20'];

        // With prefix
        yield ['v1.0.0'];
        yield ['v1.2.3'];
        yield ['v1'];
        yield ['v1.2'];

        // With pre-release
        yield ['1.0.0-alpha'];
        yield ['1.0.0-alpha.1'];
        yield ['1.0.0-0.3.7'];
        yield ['1.0.0-x.7.z.92'];
        yield ['1.0.0-alpha+001'];
        yield ['1.0.0+20130313144700'];
        yield ['1.0.0-beta+exp.sha.5114f85'];
        yield ['1.0.0+21AF26D3----117B344092BD'];

        // Complex examples
        yield ['1.2.3-alpha.1.2+build.123'];
        yield ['v1.2.3-rc.1+build.123'];
    }

    public static function getValidStrictSemVersions(): iterable
    {
        // Only valid according to official SemVer spec (no v prefix)
        yield ['0.0.0'];
        yield ['1.0.0'];
        yield ['1.2.3'];
        yield ['10.20.30'];
        
        // With pre-release
        yield ['1.0.0-alpha'];
        yield ['1.0.0-alpha.1'];
        yield ['1.0.0-0.3.7'];
        yield ['1.0.0-x.7.z.92'];
        
        // With build metadata
        yield ['1.0.0+20130313144700'];
        yield ['1.0.0+21AF26D3----117B344092BD'];
        
        // With both
        yield ['1.0.0-alpha+001'];
        yield ['1.0.0-beta+exp.sha.5114f85'];
        yield ['1.2.3-alpha.1.2+build.123'];
    }

    public static function getInvalidSemVersions(): iterable
    {
        yield ['v'];
        yield ['1.2.3.4'];
        yield ['01.2.3'];
        yield ['1.02.3'];
        yield ['1.2.03'];
        yield ['1.2-alpha'];
        yield ['1.2.3-'];
        yield ['1.2.3-+'];
        yield ['1.2.3-+123'];
        yield ['1.2.3-'];
        yield ['+invalid'];
        yield ['-invalid'];
        yield ['-invalid+invalid'];
        yield ['-invalid.01'];
        yield ['alpha'];
        yield ['1.2.3.DEV'];
        yield ['1.2-SNAPSHOT'];
        yield ['1.2.31.2.3----RC-SNAPSHOT.12.09.1--..12+788'];
        yield ['1.2-RC-SNAPSHOT'];
        yield ['1.0.0+'];
        yield ['1.0.0-'];
    }

    public static function getInvalidStrictSemVersions(): iterable
    {
        // Versions with v prefix (not allowed in strict mode)
        yield ['v1.0.0'];
        yield ['v1.2.3'];
        yield ['v1.0.0-alpha'];
        yield ['v1.0.0+20130313144700'];
        
        // Partial versions (not allowed in strict mode)
        yield ['1'];
        yield ['1.2'];
        yield ['v1'];
        yield ['v1.2'];
    }

}
