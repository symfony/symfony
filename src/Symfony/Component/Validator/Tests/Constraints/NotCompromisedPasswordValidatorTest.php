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

use Symfony\Component\Validator\Constraints\Luhn;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\NotCompromisedPasswordValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
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
    ];

    protected function createValidator()
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
        $r->setAccessible(true);
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

    public function testThresholdNotReached()
    {
        $this->validator->validate(self::PASSWORD_LEAKED, new NotCompromisedPassword(['threshold' => 10]));

        $this->assertNoViolation();
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

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testInvalidConstraint()
    {
        $this->validator->validate(null, new Luhn());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testInvalidValue()
    {
        $this->validator->validate([], new NotCompromisedPassword());
    }

    /**
     * @expectedException \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     * @expectedExceptionMessage Problem contacting the Have I been Pwned API.
     */
    public function testApiError()
    {
        $this->validator->validate(self::PASSWORD_TRIGGERING_AN_ERROR, new NotCompromisedPassword());
    }

    public function testApiErrorSkipped()
    {
        $this->validator->validate(self::PASSWORD_TRIGGERING_AN_ERROR, new NotCompromisedPassword(['skipOnError' => true]));
        $this->assertTrue(true); // No exception have been thrown
    }

    private function createHttpClientStub(): HttpClientInterface
    {
        $httpClientStub = $this->createMock(HttpClientInterface::class);
        $httpClientStub->method('request')->willReturnCallback(
            function (string $method, string $url): ResponseInterface {
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
                    ->willReturn(implode("\r\n", self::RETURN));

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
