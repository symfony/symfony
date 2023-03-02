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

use Symfony\Component\Validator\Constraints\Hostname;
use Symfony\Component\Validator\Constraints\HostnameValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Dmitrii Poddubnyi <dpoddubny@gmail.com>
 */
class HostnameValidatorTest extends ConstraintValidatorTestCase
{
    public function testNullIsValid()
    {
        $this->validator->validate(null, new Hostname());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Hostname());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new \stdClass(), new Hostname());
    }

    /**
     * @dataProvider getValidMultilevelDomains
     */
    public function testValidTldDomainsPassValidationIfTldRequired($domain)
    {
        $this->validator->validate($domain, new Hostname());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidMultilevelDomains
     */
    public function testValidTldDomainsPassValidationIfTldNotRequired($domain)
    {
        $this->validator->validate($domain, new Hostname(['requireTld' => false]));

        $this->assertNoViolation();
    }

    public static function getValidMultilevelDomains()
    {
        return [
            ['symfony.com'],
            ['example.co.uk'],
            ['example.fr'],
            ['example.com'],
            ['xn--diseolatinoamericano-66b.com'],
            ['xn--ggle-0nda.com'],
            ['www.xn--simulateur-prt-2kb.fr'],
            [sprintf('%s.com', str_repeat('a', 20))],
        ];
    }

    /**
     * @dataProvider getInvalidDomains
     */
    public function testInvalidDomainsRaiseViolationIfTldRequired($domain)
    {
        $this->validator->validate($domain, new Hostname([
            'message' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$domain.'"')
            ->setCode(Hostname::INVALID_HOSTNAME_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getInvalidDomains
     */
    public function testInvalidDomainsRaiseViolationIfTldNotRequired($domain)
    {
        $this->validator->validate($domain, new Hostname([
            'message' => 'myMessage',
            'requireTld' => false,
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$domain.'"')
            ->setCode(Hostname::INVALID_HOSTNAME_ERROR)
            ->assertRaised();
    }

    public static function getInvalidDomains()
    {
        return [
            ['acme..com'],
            ['qq--.com'],
            ['-example.com'],
            ['example-.com'],
            [sprintf('%s.com', str_repeat('a', 300))],
        ];
    }

    /**
     * @dataProvider getReservedDomains
     */
    public function testReservedDomainsPassValidationIfTldNotRequired($domain)
    {
        $this->validator->validate($domain, new Hostname(['requireTld' => false]));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getReservedDomains
     */
    public function testReservedDomainsRaiseViolationIfTldRequired($domain)
    {
        $this->validator->validate($domain, new Hostname([
            'message' => 'myMessage',
            'requireTld' => true,
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$domain.'"')
            ->setCode(Hostname::INVALID_HOSTNAME_ERROR)
            ->assertRaised();
    }

    public static function getReservedDomains()
    {
        return [
            ['example'],
            ['foo.example'],
            ['invalid'],
            ['bar.invalid'],
            ['localhost'],
            ['lol.localhost'],
            ['test'],
            ['abc.test'],
        ];
    }

    public function testReservedDomainsRaiseViolationIfTldRequiredNamed()
    {
        $this->validator->validate(
            'example',
            new Hostname(message: 'myMessage', requireTld: true)
        );

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"example"')
            ->setCode(Hostname::INVALID_HOSTNAME_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getTopLevelDomains
     */
    public function testTopLevelDomainsPassValidationIfTldNotRequired($domain)
    {
        $this->validator->validate($domain, new Hostname(['requireTld' => false]));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTopLevelDomains
     */
    public function testTopLevelDomainsRaiseViolationIfTldRequired($domain)
    {
        $this->validator->validate($domain, new Hostname([
            'message' => 'myMessage',
            'requireTld' => true,
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$domain.'"')
            ->setCode(Hostname::INVALID_HOSTNAME_ERROR)
            ->assertRaised();
    }

    public static function getTopLevelDomains()
    {
        return [
            ['com'],
            ['net'],
            ['org'],
            ['etc'],
        ];
    }

    protected function createValidator(): HostnameValidator
    {
        return new HostnameValidator();
    }
}
