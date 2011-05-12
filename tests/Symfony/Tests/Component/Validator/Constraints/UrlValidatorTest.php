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
            array('http://www.google.com'),
            array('http://www.google.com/?foo=bar'),
            array('https://google.com/'),
            array('https://google.com:80/'),
            array('http://foo.bar.us/?foo=bar'),
            array('http://foo.bar.us/?foobar[]=1'),
            array('http://foo.bar.us/?foo[]&bar[]'),
            array('http://bar2.foo.bar.us/'),
            array('http://www.symfony.com/'),
            array('http://127.0.0.1/'),
            array('http://127.0.0.1:80/'),

            // IP6 addresses
            array('http://[::1]/'),
            array('http://[::1]/?foo=bar'),
            array('http://[::1]/?foobar[]'),
            array('http://[::1]/?foo[]&bar[]'),
            array('http://[::1]:80'),
            array('http://[::1]:80/?foo=bar'),
            array('http://[::]/'),
            array('http://[fdfe:dcba:9876:ffff:fdc6:c46b:bb8f:7d4c]/'),
            array('http://[fdc6:c46b:bb8f:7d4c:fdc6:c46b:bb8f:7d4c]/'),
            array('http://[fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334]/'),
            array('http://[fe80:0000:0000:0000:0202:b3ff:fe1e:8329]/'),
            array('http://[fe80:0:0:0:202:b3ff:fe1e:8329]/'),
            array('http://[fe80::202:b3ff:fe1e:8329]/'),
            array('http://[0:0:0:0:0:0:0:0]/'),
            array('http://[2001:0db8:85a3:0000:0000:8a2e:0370:7334]/'),
            array('http://[2001:0db8:85a3:0000:0000:8a2e:0.0.0.0]'),
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
            array('foobar.example'),
            array('http:/google.com'),
            array('http://google.com::aa'),
            array('http://google.com[]'),
            array('ftp://google.com/'),

            // IP6 addresses
            array('http://::1/'),
            array('http://[]/'),
            array('http://[0:0:0:0:0:0:0:0/'),
            array('http://[0:0:0:0:0:0:0:0/]'),
            array('http://[z001:0db8:85a3:0000:0000:8a2e:0370:7334]'),
            array('http://[fe80]'),
            array('http://[fe80:8329]'),
            array('http://[2001:0db8:85a3:0000:0000:8a2e:0370:0.0.0.0]'),
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
