<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\Url;
use Symfony\Components\Validator\Constraints\UrlValidator;

class UrlValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    public function setUp()
    {
        $this->validator = new UrlValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Url()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Components\Validator\Exception\UnexpectedTypeException');

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
            array('https://google.com/'),
            array('https://google.com:80/'),
            array('http://www.symfony-project.com/'),
            array('http://127.0.0.1/'),
            array('http://127.0.0.1:80/'),
            array('ftp://google.com/foo.tgz'),
            array('ftps://google.com/foo.tgz'),
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
            array('http:/google.com'),
            array('http://google.com::aa'),
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
            'value' => 'foobar',
        ));
    }
}