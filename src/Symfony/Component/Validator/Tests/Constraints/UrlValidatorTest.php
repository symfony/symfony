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

class UrlValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new UrlValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Url());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Url());

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Url());
    }

    /**
     * @dataProvider getValidUrls
     */
    public function testValidUrls($url)
    {
        $this->validator->validate($url, new Url());

        $this->assertNoViolation();
    }

    public function getValidUrls()
    {
        return array(
            array('http://a.pl'),
            array('http://www.google.com'),
            //array('http://www.google.com.') OK as of 2.5
            array('http://www.google.museum'),
            array('https://google.com/'),
            array('https://google.com:80/'),
            array('http://www.example.coop/'),
            array('http://www.test-example.com/'),
            array('http://www.symfony.com/'),
            array('http://symfony.fake/blog/'),
            array('http://symfony.com/?'),
            array('http://symfony.com/search?type=&q=url+validator'),
            array('http://symfony.com/#'),
            array('http://symfony.com/#?'),
            array('http://www.symfony.com/doc/current/book/validation.html#supported-constraints'),
            array('http://very.long.domain.name.com/'),
            //array('http://localhost/') OK as of 2.5
            array('http://127.0.0.1/'),
            array('http://127.0.0.1:80/'),
            array('http://[::1]/'),
            array('http://[::1]:80/'),
            array('http://[1:2:3::4:5:6:7]/'),
            array('http://sãopaulo.com/'),
            array('http://xn--sopaulo-xwa.com/'),
            array('http://sãopaulo.com.br/'),
            array('http://xn--sopaulo-xwa.com.br/'),
            array('http://пример.испытание/'),
            array('http://xn--e1afmkfd.xn--80akhbyknj4f/'),
            array('http://مثال.إختبار/'),
            array('http://xn--mgbh0fb.xn--kgbechtv/'),
            array('http://例子.测试/'),
            array('http://xn--fsqu00a.xn--0zwm56d/'),
            array('http://例子.測試/'),
            array('http://xn--fsqu00a.xn--g6w251d/'),
            array('http://例え.テスト/'),
            array('http://xn--r8jz45g.xn--zckzah/'),
            array('http://مثال.آزمایشی/'),
            array('http://xn--mgbh0fb.xn--hgbk6aj7f53bba/'),
            array('http://실례.테스트/'),
            array('http://xn--9n2bp8q.xn--9t4b11yi5a/'),
            array('http://العربية.idn.icann.org/'),
            array('http://xn--ogb.idn.icann.org/'),
            array('http://xn--e1afmkfd.xn--80akhbyknj4f.xn--e1afmkfd/'),
            array('http://xn--espaa-rta.xn--ca-ol-fsay5a/'),
            array('http://xn--d1abbgf6aiiy.xn--p1ai/'),
            array('http://☎.com/'),
            array('http://username:password@symfony.com'),
            array('http://user-name@symfony.com'),
        );
    }

    /**
     * @dataProvider getInvalidUrls
     */
    public function testInvalidUrls($url)
    {
        $constraint = new Url(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($url, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$url.'"')
            ->assertRaised();
    }

    public function getInvalidUrls()
    {
        return array(
            array('google.com'),
            array('://google.com'),
            array('http ://google.com'),
            array('http:/google.com'),
            array('http://goog_le.com'),
            array('http://google.com::aa'),
            array('http://google.com:aa'),
            array('http://symfony.com?'),
            array('http://symfony.com#'),
            array('ftp://google.fr'),
            array('faked://google.fr'),
            array('http://127.0.0.1:aa/'),
            array('ftp://[::1]/'),
            array('http://[::1'),
            array('http://hello.☎/'),
            array('http://:password@symfony.com'),
            array('http://:password@@symfony.com'),
            array('http://username:passwordsymfony.com'),
            array('http://usern@me:password@symfony.com'),
        );
    }

    /**
     * @dataProvider getValidCustomUrls
     */
    public function testCustomProtocolIsValid($url)
    {
        $constraint = new Url(array(
            'protocols' => array('ftp', 'file', 'git'),
        ));

        $this->validator->validate($url, $constraint);

        $this->assertNoViolation();
    }

    public function getValidCustomUrls()
    {
        return array(
            array('ftp://google.com'),
            array('file://127.0.0.1'),
            array('git://[::1]/'),
        );
    }
}
