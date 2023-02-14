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

use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Constraints\Luhn;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\NotCompromisedPasswordValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class NotCompromisedPasswordValidatorTest extends ConstraintValidatorTestCase
{
    private const PASSWORD_TRIGGERING_AN_ERROR = 'apiError';
    private const PASSWORD_TRIGGERING_AN_ERROR_RANGE_URL = 'https://api.pwnedpasswords.com/range/3EF27'; // https://api.pwnedpasswords.com/range/3EF27 is the range for the value "apiError"
    private const PASSWORD_LEAKED = 'maman';
    private const PASSWORD_NOT_LEAKED = ']<0585"%sb^5aa$w6!b38",,72?dp3r4\45b28Hy';
    private const PASSWORD_NON_UTF8_LEAKED = 'мама';
    private const PASSWORD_NON_UTF8_NOT_LEAKED = 'м<в0dp3r4\45b28Hy';

    private const RETURN = [
        '35E033023A46402F94CFB4F654C5BFE44A1:1',
        '35F079CECCC31812288257CD770AA7968D7:53',
        '36039744C253F9B2A4E90CBEDB02EBFB82D:5', // UTF-8 leaked password: maman
        '273CA8A2A78C9B2D724144F4FAF4D221C86:6', // ISO-8859-5 leaked password: мама
        '3686792BBC66A72D40D928ED15621124CFE:7',
        '36EEC709091B810AA240179A44317ED415C:2',
        'EE6EB9C0DFA0F07098CEDB11ECC7AFF9D4E:0', // UTF-8 not leaked password: ]<0585"%sb^5aa$w6!b38",,72?dp3r4\45b28Hy
        'FC9F37E51AACD6B692A62769267590D46B8:0', // ISO-8859-5 non leaked password: м<в0dp3r4\45b28Hy
    ];

    protected function createValidator(): ConstraintValidatorInterface
    {
        // Pass HttpClient::create() instead of the mock to run the tests against the real API
        return new NotCompromisedPasswordValidator($this->createHttpClientStub());
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new NotCompromisedPassword());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new NotCompromisedPassword());

        $this->assertNoViolation();
    }

    public function testInvalidPasswordButDisabled()
    {
        $r = new \ReflectionProperty($this->validator, 'enabled');
        $r->setValue($this->validator, false);

        $this->validator->validate(self::PASSWORD_LEAKED, new NotCompromisedPassword());

        $this->assertNoViolation();
    }

    public function testInvalidPassword()
    {
        $constraint = new NotCompromisedPassword();
        $this->validator->validate(self::PASSWORD_LEAKED, $constraint);

        $this->buildViolation($constraint->message)
            ->setCode(NotCompromisedPassword::COMPROMISED_PASSWORD_ERROR)
            ->assertRaised();
    }

    public function testThresholdReached()
    {
        $constraint = new NotCompromisedPassword(['threshold' => 3]);
        $this->validator->validate(self::PASSWORD_LEAKED, $constraint);

        $this->buildViolation($constraint->message)
            ->setCode(NotCompromisedPassword::COMPROMISED_PASSWORD_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider provideConstraintsWithThreshold
     */
    public function testThresholdNotReached(NotCompromisedPassword $constraint)
    {
        $this->validator->validate(self::PASSWORD_LEAKED, $constraint);

        $this->assertNoViolation();
    }

    public static function provideConstraintsWithThreshold(): iterable
    {
        yield 'Doctrine style' => [new NotCompromisedPassword(['threshold' => 10])];
        yield 'named arguments' => [new NotCompromisedPassword(threshold: 10)];
    }

    public function testValidPassword()
    {
        $this->validator->validate(self::PASSWORD_NOT_LEAKED, new NotCompromisedPassword());

        $this->assertNoViolation();
    }

    public function testNonUtf8CharsetValid()
    {
        $validator = new NotCompromisedPasswordValidator($this->createHttpClientStub(), 'ISO-8859-5');
        $validator->validate(mb_convert_encoding(self::PASSWORD_NON_UTF8_NOT_LEAKED, 'ISO-8859-5', 'UTF-8'), new NotCompromisedPassword());

        $this->assertNoViolation();
    }

    public function testNonUtf8CharsetInvalid()
    {
        $constraint = new NotCompromisedPassword();

        $this->context = $this->createContext();

        $validator = new NotCompromisedPasswordValidator($this->createHttpClientStub(), 'ISO-8859-5');
        $validator->initialize($this->context);
        $validator->validate(mb_convert_encoding(self::PASSWORD_NON_UTF8_LEAKED, 'ISO-8859-5', 'UTF-8'), $constraint);

        $this->buildViolation($constraint->message)
            ->setCode(NotCompromisedPassword::COMPROMISED_PASSWORD_ERROR)
            ->assertRaised();
    }

    public function testInvalidPasswordCustomEndpoint()
    {
        $endpoint = 'https://password-check.internal.example.com/range/%s';
        // 50D74 - first 5 bytes of uppercase SHA1 hash of self::PASSWORD_LEAKED
        $expectedEndpointUrl = 'https://password-check.internal.example.com/range/50D74';
        $constraint = new NotCompromisedPassword();

        $this->context = $this->createContext();

        $validator = new NotCompromisedPasswordValidator(
            $this->createHttpClientStubCustomEndpoint($expectedEndpointUrl),
            'UTF-8',
            true,
            $endpoint
        );
        $validator->initialize($this->context);
        $validator->validate(self::PASSWORD_LEAKED, $constraint);

        $this->buildViolation($constraint->message)
            ->setCode(NotCompromisedPassword::COMPROMISED_PASSWORD_ERROR)
            ->assertRaised();
    }

    public function testEndpointWithInvalidValueInReturn()
    {
        $returnValue = implode(
            "\r\n",
            [
                '36039744C253F9B2A4E90CBEDB02EBFB82D:5',
                'This should not break the validator',
                '3686792BBC66A72D40D928ED15621124CFE:7',
                '36EEC709091B810AA240179A44317ED415C:2',
                '',
            ]
        );

        $validator = new NotCompromisedPasswordValidator(
            $this->createHttpClientStub($returnValue),
            'UTF-8',
            true,
            'https://password-check.internal.example.com/range/%s'
        );

        $validator->validate(self::PASSWORD_NOT_LEAKED, new NotCompromisedPassword());

        $this->assertNoViolation();
    }

    public function testInvalidConstraint()
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate(null, new Luhn());
    }

    public function testInvalidValue()
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate([], new NotCompromisedPassword());
    }

    public function testApiError()
    {
        $this->expectException(ExceptionInterface::class);
        $this->expectExceptionMessage('Problem contacting the Have I been Pwned API.');
        $this->validator->validate(self::PASSWORD_TRIGGERING_AN_ERROR, new NotCompromisedPassword());
    }

    /**
     * @dataProvider provideErrorSkippingConstraints
     */
    public function testApiErrorSkipped(NotCompromisedPassword $constraint)
    {
        $this->validator->validate(self::PASSWORD_TRIGGERING_AN_ERROR, $constraint);
        $this->assertTrue(true); // No exception have been thrown
    }

    public static function provideErrorSkippingConstraints(): iterable
    {
        yield 'Doctrine style' => [new NotCompromisedPassword(['skipOnError' => true])];
        yield 'named arguments' => [new NotCompromisedPassword(skipOnError: true)];
    }

    private function createHttpClientStub(?string $returnValue = null): HttpClientInterface
    {
        $httpClientStub = $this->createMock(HttpClientInterface::class);
        $httpClientStub->method('request')->willReturnCallback(
            function (string $method, string $url) use ($returnValue): ResponseInterface {
                if (self::PASSWORD_TRIGGERING_AN_ERROR_RANGE_URL === $url) {
                    throw new class('Problem contacting the Have I been Pwned API.') extends \Exception implements ServerExceptionInterface {
                        public function getResponse(): ResponseInterface
                        {
                            throw new \RuntimeException('Not implemented');
                        }
                    };
                }

                $responseStub = $this->createMock(ResponseInterface::class);
                $responseStub
                    ->method('getContent')
                    ->willReturn($returnValue ?? implode("\r\n", self::RETURN));

                return $responseStub;
            }
        );

        return $httpClientStub;
    }

    private function createHttpClientStubCustomEndpoint($expectedEndpoint): HttpClientInterface
    {
        $httpClientStub = $this->createMock(HttpClientInterface::class);
        $httpClientStub->method('request')->with('GET', $expectedEndpoint)->willReturnCallback(
            function (string $method, string $url): ResponseInterface {
                $responseStub = $this->createMock(ResponseInterface::class);
                $responseStub
                    ->method('getContent')
                    ->willReturn(implode("\r\n", self::RETURN));

                return $responseStub;
            }
        );

        return $httpClientStub;
    }
}
