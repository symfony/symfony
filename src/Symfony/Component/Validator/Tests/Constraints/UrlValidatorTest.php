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

class UrlValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new UrlValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new Url());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Url());
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
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($url, new Url());
    }

    public function getValidUrls()
    {
        return array(
            array('http://a.pl'),
            array('http://www.google.com'),
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
            array('http://127.0.0.1/'),
            array('http://127.0.0.1:80/'),
            array('http://[::1]/'),
            array('http://[::1]:80/'),
            array('http://[1:2:3::4:5:6:7]/'),
            array('http://sãopaulo.com/'),
            array('http://sãopaulo.com.br/'),
            array('http://пример.испытание/'),
            array('http://مثال.إختبار/'),
            array('http://例子.测试/'),
            array('http://例子.測試/'),
            array('http://例え.テスト/'),
            array('http://مثال.آزمایشی/'),
            array('http://실례.테스트/'),
            array('http://العربية.idn.icann.org/'),
            array('http://☎.com/'),
        );
    }

    /**
     * @dataProvider getInvalidUrls
     */
    public function testInvalidUrls($url)
    {
        $constraint = new Url(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $url,
            ));

        $this->validator->validate($url, $constraint);
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
        );
    }

    /**
     * @dataProvider getValidCustomUrls
     */
    public function testCustomProtocolIsValid($url)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Url(array(
            'protocols' => array('ftp', 'file', 'git')
        ));

        $this->validator->validate($url, $constraint);
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
