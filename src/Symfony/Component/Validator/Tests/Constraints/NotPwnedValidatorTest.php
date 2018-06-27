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
use Symfony\Component\Validator\Constraints\NotPwned;
use Symfony\Component\Validator\Constraints\NotPwnedValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class NotPwnedValidatorTest extends ConstraintValidatorTestCase
{
    private const PASSWORD_TRIGGERING_AN_ERROR = 'apiError';
    private const PASSWORD_TRIGGERING_AN_ERROR_RANGE_URL = 'https://api.pwnedpasswords.com/range/3EF27'; // https://api.pwnedpasswords.com/range/3EF27 is the range for the value "apiError"
    private const PASSWORD_LEAKED = 'maman';
    private const PASSWORD_NOT_LEAKED = ']<0585"%sb^5aa$w6!b38",,72?dp3r4\45b28Hy';

    private const RETURN = [
        '35E033023A46402F94CFB4F654C5BFE44A1:1',
        '35F079CECCC31812288257CD770AA7968D7:53',
        '36039744C253F9B2A4E90CBEDB02EBFB82D:5', // this is the matching line, password: maman
        '3686792BBC66A72D40D928ED15621124CFE:7',
        '36EEC709091B810AA240179A44317ED415C:2',
    ];

    protected function createValidator()
    {
        $httpClientStub = $this->createMock(HttpClientInterface::class);
        $httpClientStub->method('request')->will(
            $this->returnCallback(function (string $method, string $url): ResponseInterface {
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
            })
        );

        // Pass HttpClient::create() instead of this mock to run the tests against the real API
        return new NotPwnedValidator($httpClientStub);
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new NotPwned());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new NotPwned());

        $this->assertNoViolation();
    }

    public function testInvalidPassword()
    {
        $constraint = new NotPwned();
        $this->validator->validate(self::PASSWORD_LEAKED, $constraint);

        $this->buildViolation($constraint->message)
            ->setCode(NotPwned::PWNED_ERROR)
            ->assertRaised();
    }

    public function testThresholdReached()
    {
        $constraint = new NotPwned(['threshold' => 3]);
        $this->validator->validate(self::PASSWORD_LEAKED, $constraint);

        $this->buildViolation($constraint->message)
            ->setCode(NotPwned::PWNED_ERROR)
            ->assertRaised();
    }

    public function testThresholdNotReached()
    {
        $this->validator->validate(self::PASSWORD_LEAKED, new NotPwned(['threshold' => 10]));

        $this->assertNoViolation();
    }

    public function testValidPassword()
    {
        $this->validator->validate(self::PASSWORD_NOT_LEAKED, new NotPwned());

        $this->assertNoViolation();
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
        $this->validator->validate([], new NotPwned());
    }

    /**
     * @expectedException \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     * @expectedExceptionMessage Problem contacting the Have I been Pwned API.
     */
    public function testApiError()
    {
        $this->validator->validate(self::PASSWORD_TRIGGERING_AN_ERROR, new NotPwned());
    }

    public function testApiErrorSkipped()
    {
        $this->validator->validate(self::PASSWORD_TRIGGERING_AN_ERROR, new NotPwned(['skipOnError' => true]));
        $this->assertTrue(true); // No exception have been thrown
    }
}
