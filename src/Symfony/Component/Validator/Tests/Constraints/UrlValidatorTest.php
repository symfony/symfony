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

use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\UrlValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class UrlValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): UrlValidator
    {
        return new UrlValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Url(requireTld: true));

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Url(requireTld: true));

        $this->assertNoViolation();
    }

    public function testEmptyStringFromObjectIsValid()
    {
        $this->validator->validate(new EmailProvider(), new Url(requireTld: true));

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new Url(requireTld: true));
    }

    /**
     * @dataProvider getValidUrls
     */
    public function testValidUrls($url)
    {
        $this->validator->validate($url, new Url(requireTld: false));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidUrls
     */
    public function testValidUrlsWithNewLine($url)
    {
        $this->validator->validate($url."\n", new Url(requireTld: false));

        $this->buildViolation('This value is not a valid URL.')
            ->setParameter('{{ value }}', '"'.$url."\n".'"')
            ->setCode(Url::INVALID_URL_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidUrlsWithWhitespaces
     */
    public function testValidUrlsWithWhitespaces($url)
    {
        $this->validator->validate($url, new Url(
            normalizer: 'trim',
            requireTld: true,
        ));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidRelativeUrls
     * @dataProvider getValidUrls
     */
    public function testValidRelativeUrl($url)
    {
        $constraint = new Url(
            relativeProtocol: true,
            requireTld: false,
        );

        $this->validator->validate($url, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidRelativeUrls
     * @dataProvider getValidUrls
     */
    public function testValidRelativeUrlWithNewLine(string $url)
    {
        $constraint = new Url(relativeProtocol: true, requireTld: false);

        $this->validator->validate($url."\n", $constraint);

        $this->buildViolation('This value is not a valid URL.')
            ->setParameter('{{ value }}', '"'.$url."\n".'"')
            ->setCode(Url::INVALID_URL_ERROR)
            ->assertRaised();
    }

    public static function getValidRelativeUrls()
    {
        return [
            ['//example.com'],
            ['//examp_le.com'],
            ['//symfony.fake/blog/'],
            ['//symfony.com/search?type=&q=url+validator'],
        ];
    }

    public static function getValidUrls()
    {
        return [
            ['http://a.pl'],
            ['http://www.example.com'],
            ['http://tt.example.com'],
            ['http://m.example.com'],
            ['http://m.m.m.example.com'],
            ['http://example.m.example.com'],
            ['https://long-string_with+symbols.m.example.com'],
            ['http://www.example.com.'],
            ['http://www.example.museum'],
            ['https://example.com/'],
            ['https://example.com:80/'],
            ['http://examp_le.com'],
            ['http://www.sub_domain.examp_le.com'],
            ['http://www.example.coop/'],
            ['http://www.test-example.com/'],
            ['http://www.symfony.com/'],
            ['http://symfony.fake/blog/'],
            ['http://symfony.com/?'],
            ['http://symfony.com/search?type=&q=url+validator'],
            ['http://symfony.com/#'],
            ['http://symfony.com/#?'],
            ['http://www.symfony.com/doc/current/book/validation.html#supported-constraints'],
            ['http://very.long.domain.name.com/'],
            ['http://localhost/'],
            ['http://myhost123/'],
            ['http://internal-api'],
            ['http://internal-api.'],
            ['http://internal-api/'],
            ['http://internal-api/path'],
            ['http://127.0.0.1/'],
            ['http://127.0.0.1:80/'],
            ['http://[::1]/'],
            ['http://[::1]:80/'],
            ['http://[1:2:3::4:5:6:7]/'],
            ['http://sãopaulo.com/'],
            ['http://xn--sopaulo-xwa.com/'],
            ['http://sãopaulo.com.br/'],
            ['http://xn--sopaulo-xwa.com.br/'],
            ['http://пример.испытание/'],
            ['http://xn--e1afmkfd.xn--80akhbyknj4f/'],
            ['http://مثال.إختبار/'],
            ['http://xn--mgbh0fb.xn--kgbechtv/'],
            ['http://例子.测试/'],
            ['http://xn--fsqu00a.xn--0zwm56d/'],
            ['http://例子.測試/'],
            ['http://xn--fsqu00a.xn--g6w251d/'],
            ['http://例え.テスト/'],
            ['http://xn--r8jz45g.xn--zckzah/'],
            ['http://مثال.آزمایشی/'],
            ['http://xn--mgbh0fb.xn--hgbk6aj7f53bba/'],
            ['http://실례.테스트/'],
            ['http://xn--9n2bp8q.xn--9t4b11yi5a/'],
            ['http://العربية.idn.icann.org/'],
            ['http://xn--ogb.idn.icann.org/'],
            ['http://xn--e1afmkfd.xn--80akhbyknj4f.xn--e1afmkfd/'],
            ['http://xn--espaa-rta.xn--ca-ol-fsay5a/'],
            ['http://xn--d1abbgf6aiiy.xn--p1ai/'],
            ['http://☎.com/'],
            ['http://username:password@symfony.com'],
            ['http://user.name:password@symfony.com'],
            ['http://user_name:pass_word@symfony.com'],
            ['http://username:pass.word@symfony.com'],
            ['http://user.name:pass.word@symfony.com'],
            ['http://user-name@symfony.com'],
            ['http://user_name@symfony.com'],
            ['http://u%24er:password@symfony.com'],
            ['http://user:pa%24%24word@symfony.com'],
            ['http://symfony.com?'],
            ['http://symfony.com?query=1'],
            ['http://symfony.com/?query=1'],
            ['http://symfony.com#'],
            ['http://symfony.com#fragment'],
            ['http://symfony.com/#fragment'],
            ['http://symfony.com/#one_more%20test'],
            ['http://example.com/exploit.html?hello[0]=test'],
            ['http://বিডিআইএ.বাংলা'],
            ['http://www.example.com/คนแซ่ลี้/'],
            ['http://www.example.com/か/'],
        ];
    }

    public static function getValidUrlsWithWhitespaces()
    {
        return [
            ["\x20http://www.example.com"],
            ["\x09\x09http://www.example.com."],
            ["http://symfony.fake/blog/\x0A"],
            ["http://symfony.com/search?type=&q=url+validator\x0D\x0D"],
            ["\x00https://example.com:80\x00"],
            ["\x0B\x0Bhttp://username:password@symfony.com\x0B\x0B"],
        ];
    }

    /**
     * @dataProvider getInvalidUrls
     */
    public function testInvalidUrls($url)
    {
        $constraint = new Url(
            message: 'myMessage',
            requireTld: false,
        );

        $this->validator->validate($url, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$url.'"')
            ->setCode(Url::INVALID_URL_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getInvalidRelativeUrls
     * @dataProvider getInvalidUrls
     */
    public function testInvalidRelativeUrl($url)
    {
        $constraint = new Url(
            message: 'myMessage',
            relativeProtocol: true,
            requireTld: false,
        );

        $this->validator->validate($url, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$url.'"')
            ->setCode(Url::INVALID_URL_ERROR)
            ->assertRaised();
    }

    public static function getInvalidRelativeUrls()
    {
        return [
            ['/example.com'],
            ['//example.com::aa'],
            ['//example.com:aa'],
            ['//127.0.0.1:aa/'],
            ['//[::1'],
            ['//hello.☎/'],
            ['//:password@symfony.com'],
            ['//:password@@symfony.com'],
            ['//username:passwordsymfony.com'],
            ['//usern@me:password@symfony.com'],
            ['//example.com/exploit.html?<script>alert(1);</script>'],
            ['//example.com/exploit.html?hel lo'],
            ['//example.com/exploit.html?not_a%hex'],
            ['//'],
        ];
    }

    public static function getInvalidUrls()
    {
        return [
            ['example.com'],
            ['://example.com'],
            ['http ://example.com'],
            ['http:/example.com'],
            ['http://example.com::aa'],
            ['http://example.com:aa'],
            ['ftp://example.fr'],
            ['faked://example.fr'],
            ['http://127.0.0.1:aa/'],
            ['ftp://[::1]/'],
            ['http://[::1'],
            ['http://☎'],
            ['http://☎.'],
            ['http://☎/'],
            ['http://☎/path'],
            ['http://hello.☎'],
            ['http://hello.☎.'],
            ['http://hello.☎/'],
            ['http://hello.☎/path'],
            ['http://:password@symfony.com'],
            ['http://:password@@symfony.com'],
            ['http://username:passwordsymfony.com'],
            ['http://usern@me:password@symfony.com'],
            ['http://nota%hex:password@symfony.com'],
            ['http://username:nota%hex@symfony.com'],
            ['http://example.com/exploit.html?<script>alert(1);</script>'],
            ['http://example.com/exploit.html?hel lo'],
            ['http://example.com/exploit.html?not_a%hex'],
            ['http://'],
            ['http://www..com'],
            ['http://www..example.com'],
            ['http://www..m.example.com'],
            ['http://.m.example.com'],
            ['http://wwww.example..com'],
            ['http://.www.example.com'],
            ['http://example.co-'],
            ['http://example.co-/path'],
            ['http:///path'],
        ];
    }

    /**
     * @dataProvider getValidCustomUrls
     */
    public function testCustomProtocolIsValid($url, $requireTld)
    {
        $constraint = new Url(
            protocols: ['ftp', 'file', 'git'],
            requireTld: $requireTld,
        );

        $this->validator->validate($url, $constraint);

        $this->assertNoViolation();
    }

    public static function getValidCustomUrls()
    {
        return [
            ['ftp://example.com', true],
            ['file://127.0.0.1', false],
            ['git://[::1]/', false],
        ];
    }

    /**
     * @dataProvider getUrlsForRequiredTld
     */
    public function testRequiredTld(string $url, bool $requireTld, bool $isValid)
    {
        $constraint = new Url(requireTld: $requireTld);

        $this->validator->validate($url, $constraint);

        if ($isValid) {
            $this->assertNoViolation();
        } else {
            $this->buildViolation($constraint->tldMessage)
                ->setParameter('{{ value }}', '"'.$url.'"')
                ->setCode(Url::MISSING_TLD_ERROR)
                ->assertRaised();
        }
    }

    public static function getUrlsForRequiredTld(): iterable
    {
        yield ['https://aaa', true, false];
        yield ['https://aaa', false, true];
        yield ['https://localhost', true, false];
        yield ['https://localhost', false, true];
        yield ['http://127.0.0.1', false, true];
        yield ['http://127.0.0.1', true, false];
        yield ['http://user.pass@local', false, true];
        yield ['http://user.pass@local', true, false];
        yield ['https://example.com', true, true];
        yield ['https://example.com', false, true];
        yield ['http://foo/bar.png', false, true];
        yield ['http://foo/bar.png', true, false];
        yield ['https://example.com.org', true, true];
        yield ['https://example.com.org', false, true];
    }
}

class EmailProvider
{
    public function __toString(): string
    {
        return '';
    }
}
