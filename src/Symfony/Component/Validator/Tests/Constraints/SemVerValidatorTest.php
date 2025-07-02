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
        $this->validator->validate($version, new SemVer(strict: false));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidStrictSemVersions
     */
    public function testValidStrictSemVersions(string $version)
    {
        $this->validator->validate($version, new SemVer());

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
        $constraint = new SemVer(message: 'myMessage');

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

    /**
     * @dataProvider getValidVersionsWithMinMax
     */
    public function testValidVersionsWithMinMax(string $version, ?string $min, ?string $max, bool $strict)
    {
        $constraint = new SemVer(strict: $strict, min: $min, max: $max);

        $this->validator->validate($version, $constraint);

        $this->assertNoViolation();
    }

    public static function getValidVersionsWithMinMax(): iterable
    {
        // Test min only
        yield ['2.0.0', '1.0.0', null, true];
        yield ['2.0.0', '2.0.0', null, true];
        yield ['2.0.1', '2.0.0', null, true];
        
        // Test max only
        yield ['1.0.0', null, '2.0.0', true];
        yield ['2.0.0', null, '2.0.0', true];
        yield ['1.9.9', null, '2.0.0', true];
        
        // Test both min and max
        yield ['1.5.0', '1.0.0', '2.0.0', true];
        yield ['1.0.0', '1.0.0', '2.0.0', true];
        yield ['2.0.0', '1.0.0', '2.0.0', true];
        
        // Test with pre-release versions
        yield ['1.0.0-alpha', '1.0.0-alpha', null, true];
        yield ['1.0.0', '1.0.0-alpha', null, true];
        yield ['1.0.0-beta', '1.0.0-alpha', null, true];
        yield ['1.0.0-alpha.2', '1.0.0-alpha.1', null, true];
        
        // Test with loose versions
        yield ['v2.0', 'v1.0', null, false];
        yield ['2', '1', null, false];
        yield ['v1.5', 'v1.0', 'v2.0', false];
    }

    /**
     * @dataProvider getTooLowVersions
     */
    public function testTooLowVersions(string $version, string $min, bool $strict)
    {
        $constraint = new SemVer(
            minMessage: 'myMessage',
            strict: $strict,
            min: $min
        );

        $this->validator->validate($version, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$version.'"')
            ->setParameter('{{ min }}', $min)
            ->setCode(SemVer::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public static function getTooLowVersions(): iterable
    {
        yield ['0.9.9', '1.0.0', true];
        yield ['1.0.0', '1.0.1', true];
        yield ['1.0.0-alpha', '1.0.0', true];
        yield ['1.0.0-alpha.1', '1.0.0-alpha.2', true];
        
        // Test with loose versions
        yield ['v0.9', 'v1.0', false];
        yield ['1', '2', false];
    }

    /**
     * @dataProvider getTooHighVersions
     */
    public function testTooHighVersions(string $version, string $max, bool $strict)
    {
        $constraint = new SemVer(
            maxMessage: 'myMessage',
            strict: $strict,
            max: $max
        );

        $this->validator->validate($version, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$version.'"')
            ->setParameter('{{ max }}', $max)
            ->setCode(SemVer::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    public static function getTooHighVersions(): iterable
    {
        yield ['2.0.1', '2.0.0', true];
        yield ['1.0.1', '1.0.0', true];
        yield ['1.0.0', '1.0.0-alpha', true];
        yield ['1.0.0-alpha.2', '1.0.0-alpha.1', true];
        
        // Test with loose versions
        yield ['v2.1', 'v2.0', false];
        yield ['3', '2', false];
    }

    public function testInvalidMinOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "min" option value "invalid" is not a valid semantic version according to the "strict" option.');

        $constraint = new SemVer(min: 'invalid');
        $this->validator->validate('1.0.0', $constraint);
    }

    public function testInvalidMaxOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "max" option value "invalid" is not a valid semantic version according to the "strict" option.');

        $constraint = new SemVer(max: 'invalid');
        $this->validator->validate('1.0.0', $constraint);
    }

    public function testMinMaxOptionsFollowStrictMode()
    {
        // In strict mode, min/max with 'v' prefix should be invalid
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "min" option value "v1.0.0" is not a valid semantic version according to the "strict" option.');

        $constraint = new SemVer(strict: true, min: 'v1.0.0');
        $this->validator->validate('2.0.0', $constraint);
    }

}
