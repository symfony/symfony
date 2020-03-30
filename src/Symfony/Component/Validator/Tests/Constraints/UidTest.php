<?php

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Uid;

class UidTest extends TestCase
{
    public function testNotArrayTypesTriggersException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The "types" parameter should be an array.');
        $uid = new Uid(['types' => 'foo']);
    }

    public function testInvalidTypeTriggerException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The "types" parameter is not valid.');
        $uid = new Uid(['types' => ['foo']]);
    }

    public function testNotArrayVersionsTriggersException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The "versions" parameter should be an array.');
        $uid = new Uid(['versions' => 'foo']);
    }

    public function testInvalidVersionTriggerException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The "versions" parameter is not valid.');
        $uid = new Uid(['versions' => [7]]);
    }

    public function testNormalizerCanBeSet()
    {
        $email = new Uid(['normalizer' => 'trim']);

        $this->assertEquals('trim', $email->normalizer);
    }

    public function testInvalidNormalizerThrowsException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("string" given).');
        new Uid(['normalizer' => 'Unknown Callable']);
    }

    public function testInvalidNormalizerObjectThrowsException()
    {
        $this->expectException('Symfony\Component\Validator\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The "normalizer" option must be a valid callable ("stdClass" given).');
        new Uid(['normalizer' => new \stdClass()]);
    }
}
