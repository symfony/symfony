<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\SMimePart;
use Symfony\Component\Mime\Part\TextPart;

class AbstractPartTest extends TestCase
{
    public function testSerializeKeepsThePropertiesOfSubclasses()
    {
        $part = new AbstractPartTestChildPart('private', 'protected', 'public', 'typed', 'child');
        $part->getHeaders()->addTextHeader('X-Foo', 'bar');

        $unserialized = unserialize(serialize($part));

        $this->assertSame(['private', 'protected', 'public', 'typed', 'child'], $unserialized->getState());
        $this->assertSame($part->toString(), $unserialized->toString());
    }

    public function testSerializeKeepsThePropertiesOfMultipartSubclasses()
    {
        $part = new AbstractPartTestMultipartPart('state', new TextPart('content'));
        $expected = $part->toString();

        $unserialized = unserialize(serialize($part));

        $this->assertSame('state', $unserialized->state);
        $this->assertSame($expected, $unserialized->toString());
    }

    public function testUnserializeNativePayloadOfSubclass()
    {
        $headers = (new Headers())->addTextHeader('X-Foo', 'bar');
        $part = unserialize($this->createPayload(AbstractPartTestPart::class, [
            "\0".AbstractPart::class."\0headers" => $headers,
            "\0".AbstractPartTestPart::class."\0private" => 'private',
            "\0*\0protected" => 'protected',
            'public' => 'public',
            "\0".AbstractPartTestPart::class."\0typed" => 'typed',
        ]));

        $this->assertSame(['private', 'protected', 'public', 'typed'], $part->getState());
        $this->assertEquals($headers, $part->getHeaders());
    }

    public function testUnserializeRejectsObjectInTypedStringPropertyOfSubclass()
    {
        $data = (new AbstractPartTestPart())->__serialize();
        $data["\0".AbstractPartTestPart::class."\0typed"] = new AbstractPartTestToStringGadget();
        AbstractPartTestToStringGadget::$fired = false;

        try {
            unserialize($this->createPayload(AbstractPartTestPart::class, $data));
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(AbstractPartTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }

    #[DataProvider('provideForeignKeys')]
    public function testUnserializeRejectsPropertiesOfOtherClasses(string $key)
    {
        $data = (new AbstractPartTestPart())->__serialize();
        $data[$key] = [];

        $this->expectException(\BadMethodCallException::class);

        unserialize($this->createPayload(AbstractPartTestPart::class, $data));
    }

    public static function provideForeignKeys(): iterable
    {
        yield ["\0".AbstractMultipartPart::class."\0parts"];
        yield ["\0".AbstractPart::class."\0foo"];
        yield ["\0stdClass\0foo"];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testUnserializeCallsWakeupOfMultipartSubclasses()
    {
        $payload = serialize(new AbstractPartTestWakeupMultipartPart(new TextPart('content')));

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/mime 7.4: Implementing "%s::__wakeup()" is deprecated, use "__unserialize()" instead.', AbstractPartTestWakeupMultipartPart::class));

        $part = unserialize($payload);

        $this->assertSame(1, $part->wakeups);
        $this->assertCount(1, $part->getParts());
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyPayloadsWithObjectInTypedStringProperty')]
    public function testUnserializeLegacyPayloadRejectsObjectInTypedStringProperty(string $class, array $data, string $deprecation)
    {
        AbstractPartTestToStringGadget::$fired = false;

        $this->expectUserDeprecationMessage($deprecation);

        try {
            unserialize($this->createPayload($class, $data));
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(AbstractPartTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }

    public static function provideLegacyPayloadsWithObjectInTypedStringProperty(): iterable
    {
        $abstractPartDeprecation = 'Since symfony/mime 7.4: Passing more than just key "headers" to "%s::__unserialize()" is deprecated, populate properties in "%s::__unserialize()" instead.';
        $extraKeysDeprecation = 'Since symfony/mime 7.4: Passing extra keys to "%s::__unserialize()" is deprecated, populate properties in "%s::__unserialize()" instead.';

        $parts = [
            AbstractPartTestPart::class => [(new AbstractPartTestPart('private', 'protected', 'public', 'typed'))->__serialize(), ['typed'], \sprintf($abstractPartDeprecation, AbstractPart::class, AbstractPartTestPart::class)],
            TextPart::class => [(new TextPart('content'))->setDisposition('inline')->setName('file.txt')->__serialize(), ['charset', 'subtype', 'disposition', 'name', 'encoding'], \sprintf($extraKeysDeprecation, TextPart::class, TextPart::class)],
            DataPart::class => [(new DataPart('content', 'file.txt', 'text/plain'))->setContentId('foo@example.com')->__serialize(), ['filename', 'mediaType', 'cid'], \sprintf($extraKeysDeprecation, DataPart::class, DataPart::class)],
            SMimePart::class => [(new SMimePart('content', 'application', 'pkcs7-mime', []))->__serialize(), ['body', 'type', 'subtype'], \sprintf($extraKeysDeprecation, SMimePart::class, SMimePart::class)],
        ];
        unset($parts[AbstractPartTestPart::class][0]['headers']);

        foreach ($parts as $class => [$template, $properties, $deprecation]) {
            foreach ($properties as $property) {
                foreach (['plain' => '', 'protected' => "\0*\0", 'private' => "\0{$class}\0", 'foreign' => "\0stdClass\0"] as $form => $prefix) {
                    if ('' === $prefix && AbstractPartTestPart::class !== $class) {
                        continue;
                    }

                    $data = $template;
                    $data[$prefix.$property] = new AbstractPartTestToStringGadget();

                    yield "{$class}::\${$property} {$form}" => [$class, $data, $deprecation];
                }
            }
        }

        $data = (new DataPart('content', 'file.txt', 'text/plain'))->__serialize();
        $data['_parent']['filename'] = new AbstractPartTestToStringGadget();

        yield DataPart::class.'::$filename in _parent' => [DataPart::class, $data, \sprintf($extraKeysDeprecation, TextPart::class, DataPart::class)];

        $data = (new AbstractPartTestMultipartPart('state', new TextPart('content')))->__serialize();
        unset($data['headers']);
        $data["\0stdClass\0state"] = new AbstractPartTestToStringGadget();

        yield AbstractPartTestMultipartPart::class.'::$state foreign' => [AbstractPartTestMultipartPart::class, $data, \sprintf($abstractPartDeprecation, AbstractPart::class, AbstractPartTestMultipartPart::class)];
    }

    private function createPayload(string $class, array $data): string
    {
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen($class), $class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }

        return $payload.'}';
    }
}

class AbstractPartTestPart extends AbstractPart
{
    public $public;
    protected $protected;
    private $private;
    private string $typed;

    public function __construct($private = null, $protected = null, $public = null, string $typed = '')
    {
        parent::__construct();

        $this->private = $private;
        $this->protected = $protected;
        $this->public = $public;
        $this->typed = $typed;
    }

    public function getState(): array
    {
        return [$this->private, $this->protected, $this->public, $this->typed];
    }

    public function bodyToString(): string
    {
        return 'body';
    }

    public function bodyToIterable(): iterable
    {
        yield 'body';
    }

    public function getMediaType(): string
    {
        return 'text';
    }

    public function getMediaSubtype(): string
    {
        return 'plain';
    }
}

class AbstractPartTestChildPart extends AbstractPartTestPart
{
    private $private;

    public function __construct($parentPrivate, $protected, $public, string $typed, $private)
    {
        parent::__construct($parentPrivate, $protected, $public, $typed);

        $this->private = $private;
    }

    public function getState(): array
    {
        return [...parent::getState(), $this->private];
    }
}

class AbstractPartTestMultipartPart extends AbstractMultipartPart
{
    public function __construct(
        public string $state,
        AbstractPart ...$parts,
    ) {
        parent::__construct(...$parts);
    }

    public function getMediaSubtype(): string
    {
        return 'mixed';
    }
}

class AbstractPartTestWakeupMultipartPart extends AbstractMultipartPart
{
    public int $wakeups = 0;

    public function getMediaSubtype(): string
    {
        return 'mixed';
    }

    public function __wakeup(): void
    {
        ++$this->wakeups;
    }
}

class AbstractPartTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}
