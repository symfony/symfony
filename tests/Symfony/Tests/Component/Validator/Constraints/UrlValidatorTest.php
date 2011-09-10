<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\UrlValidator;

class UrlValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new UrlValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Url()));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Url()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new Url());
    }

    /**
     * @dataProvider getValidUrls
     */
    public function testValidUrls($url)
    {
        $this->assertTrue($this->validator->isValid($url, new Url()));
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
            array('http://symfony.com/search?type=&q=url+validator'),
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
        $this->assertFalse($this->validator->isValid($url, new Url()));
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
        $constraint = new Url(array(
            'protocols' => array('ftp', 'file', 'git')
        ));

        $this->assertTrue($this->validator->isValid($url, $constraint));
    }

    public function getValidCustomUrls()
    {
        return array(
            array('ftp://google.com'),
            array('file://127.0.0.1'),
            array('git://[::1]/'),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Url(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 'foobar',
        ));
    }
}
