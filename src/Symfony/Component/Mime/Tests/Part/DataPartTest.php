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
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\IdentificationHeader;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class DataPartTest extends TestCase
{
    public function testConstructor()
    {
        $p = new DataPart('content');
        $this->assertEquals('content', $p->getBody());
        $this->assertEquals(base64_encode('content'), $p->bodyToString());
        $this->assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        // bodyToIterable() can be called several times
        $this->assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals('application', $p->getMediaType());
        $this->assertEquals('octet-stream', $p->getMediaSubType());

        $p = new DataPart('content', null, 'text/html');
        $this->assertEquals('text', $p->getMediaType());
        $this->assertEquals('html', $p->getMediaSubType());
    }

    public function testConstructorWithResource()
    {
        $f = fopen('php://memory', 'r+', false);
        fwrite($f, 'content');
        rewind($f);
        $p = new DataPart($f);
        $this->assertEquals('content', $p->getBody());
        $this->assertEquals(base64_encode('content'), $p->bodyToString());
        $this->assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        fclose($f);
    }

    public function testConstructorWithNonStringOrResource()
    {
        $this->expectException(\TypeError::class);
        new DataPart(new \stdClass());
    }

    public function testHeaders()
    {
        $p = new DataPart('content');
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'application/octet-stream'),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment')
        ), $p->getPreparedHeaders());

        $p = new DataPart('content', 'photo.jpg', 'text/html');
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/html', ['name' => 'photo.jpg']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'photo.jpg', 'filename' => 'photo.jpg'])
        ), $p->getPreparedHeaders());
    }

    public function testAsInline()
    {
        $p = new DataPart('content', 'photo.jpg', 'text/html');
        $p->asInline();
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/html', ['name' => 'photo.jpg']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'inline', ['name' => 'photo.jpg', 'filename' => 'photo.jpg'])
        ), $p->getPreparedHeaders());
    }

    public function testAsInlineWithCID()
    {
        $p = new DataPart('content', 'photo.jpg', 'text/html');
        $p->asInline();
        $cid = $p->getContentId();
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/html', ['name' => 'photo.jpg']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'inline', ['name' => 'photo.jpg', 'filename' => 'photo.jpg']),
            new IdentificationHeader('Content-ID', $cid)
        ), $p->getPreparedHeaders());
    }

    public function testFromPath()
    {
        $p = DataPart::fromPath($file = __DIR__.'/../Fixtures/mimetypes/test.gif');
        $content = file_get_contents($file);
        $this->assertEquals($content, $p->getBody());
        $this->assertEquals(base64_encode($content), $p->bodyToString());
        $this->assertEquals(base64_encode($content), implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals('image', $p->getMediaType());
        $this->assertEquals('gif', $p->getMediaSubType());
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'image/gif', ['name' => 'test.gif']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'test.gif', 'filename' => 'test.gif'])
        ), $p->getPreparedHeaders());
    }

    public function testFromPathWithMeta()
    {
        $p = DataPart::fromPath($file = __DIR__.'/../Fixtures/mimetypes/test.gif', 'photo.gif', 'image/jpeg');
        $content = file_get_contents($file);
        $this->assertEquals($content, $p->getBody());
        $this->assertEquals(base64_encode($content), $p->bodyToString());
        $this->assertEquals(base64_encode($content), implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals('image', $p->getMediaType());
        $this->assertEquals('jpeg', $p->getMediaSubType());
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'image/jpeg', ['name' => 'photo.gif']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'photo.gif', 'filename' => 'photo.gif'])
        ), $p->getPreparedHeaders());
    }

    public function testFromPathWithNotAFile()
    {
        $this->expectException(InvalidArgumentException::class);
        DataPart::fromPath(__DIR__.'/../Fixtures/mimetypes/');
    }

    public function testFromPathWithUrl()
    {
        if (!\in_array('http', stream_get_wrappers(), true)) {
            $this->markTestSkipped('"http" stream wrapper is not enabled.');
        }

        $finder = new PhpExecutableFinder();
        $process = new Process(array_merge([$finder->find(false)], $finder->findArguments(), ['-dopcache.enable=0', '-dvariables_order=EGPCS', '-S', 'localhost:8856']));
        $process->setWorkingDirectory(__DIR__.'/../Fixtures/web');
        $process->start();

        try {
            do {
                usleep(50000);
            } while (!@fopen('http://localhost:8856', 'r'));
            $p = DataPart::fromPath($file = 'http://localhost:8856/logo_symfony_header.png');
            $content = file_get_contents($file);
            $this->assertEquals($content, $p->getBody());
            $maxLineLength = 76;
            $this->assertEquals(substr(base64_encode($content), 0, $maxLineLength), substr($p->bodyToString(), 0, $maxLineLength));
            $this->assertEquals(substr(base64_encode($content), 0, $maxLineLength), substr(implode('', iterator_to_array($p->bodyToIterable())), 0, $maxLineLength));
            $this->assertEquals('image', $p->getMediaType());
            $this->assertEquals('png', $p->getMediaSubType());
            $this->assertEquals(new Headers(
                new ParameterizedHeader('Content-Type', 'image/png', ['name' => 'logo_symfony_header.png']),
                new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
                new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'logo_symfony_header.png', 'filename' => 'logo_symfony_header.png'])
            ), $p->getPreparedHeaders());
        } finally {
            $process->stop();
        }
    }

    public function testHasContentId()
    {
        $p = new DataPart('content');
        $this->assertFalse($p->hasContentId());
        $p->getContentId();
        $this->assertTrue($p->hasContentId());
    }

    public function testSetContentId()
    {
        $p = new DataPart('content');
        $p->setContentId('test@test');
        $this->assertTrue($p->hasContentId());
        $this->assertSame('test@test', $p->getContentId());
    }

    public function testSetContentIdInvalid()
    {
        $this->expectException(InvalidArgumentException::class);

        $p = new DataPart('content');
        $p->setContentId('test');
    }

    public function testGetFilename()
    {
        $p = new DataPart('content', null);
        self::assertNull($p->getFilename());

        $p = new DataPart('content', 'filename');
        self::assertSame('filename', $p->getFilename());
    }

    public function testGetContentType()
    {
        $p = new DataPart('content');
        self::assertSame('application/octet-stream', $p->getContentType());

        $p = new DataPart('content', null, 'application/pdf');
        self::assertSame('application/pdf', $p->getContentType());
    }

    public function testSerialize()
    {
        $r = fopen('php://memory', 'r+', false);
        fwrite($r, 'Text content');
        rewind($r);

        $p = new DataPart($r);
        $p->getHeaders()->addTextHeader('foo', 'bar');
        $expected = clone $p;
        $this->assertEquals($expected->toString(), unserialize(serialize($p))->toString());
    }

    public function testSerializePreservesContentId()
    {
        $p = new DataPart('content', 'image.jpg', 'image/jpeg');
        $p->asInline();
        $cid = $p->getContentId();

        $unserialized = unserialize(serialize($p));

        $this->assertTrue($unserialized->hasContentId());
        $this->assertSame($cid, $unserialized->getContentId());
    }

    public function testUnserializeNativePayloadWithContentId()
    {
        $data = [
            "\0*\0_headers" => new Headers(),
            "\0*\0_parent" => ['body' => 'content', 'charset' => null, 'subtype' => 'pdf', 'disposition' => 'attachment', 'name' => 'file.pdf', 'encoding' => 'base64'],
            "\0".DataPart::class."\0filename" => 'file.pdf',
            "\0".DataPart::class."\0mediaType" => 'application',
            "\0".DataPart::class."\0cid" => 'foo@example.com',
        ];
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(DataPart::class), DataPart::class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }

        $part = unserialize($payload.'}');

        $this->assertSame((new DataPart('content', 'file.pdf', 'application/pdf'))->setContentId('foo@example.com')->toString(), $part->toString());
    }

    #[DataProvider('provideTrampolineKeys')]
    public function testUnserializeRejectsObjectInTypedStringProperty(string $key)
    {
        $template = (new DataPart('content'))->__serialize();
        $template[$key] = new DataPartTestToStringGadget();
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(DataPart::class), DataPart::class, \count($template));
        foreach ($template as $k => $v) {
            $payload .= serialize($k).serialize($v);
        }
        $payload .= '}';
        DataPartTestToStringGadget::$fired = false;

        try {
            unserialize($payload);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(DataPartTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }

    public static function provideTrampolineKeys(): iterable
    {
        yield ['filename'];
        yield ['mediaType'];
        yield ['cid'];
    }

    #[DataProvider('provideNonStringValues')]
    public function testUnserializeRejectsNonStringValue(string $key, mixed $value)
    {
        $template = (new DataPart('content'))->__serialize();
        $template[$key] = $value;
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(DataPart::class), DataPart::class, \count($template));
        foreach ($template as $k => $v) {
            $payload .= serialize($k).serialize($v);
        }

        $this->expectException(\BadMethodCallException::class);

        unserialize($payload.'}');
    }

    public static function provideNonStringValues(): iterable
    {
        foreach (['filename', 'mediaType', 'cid'] as $key) {
            yield $key.' int' => [$key, 123];
            yield $key.' object' => [$key, new \stdClass()];
        }

        yield 'mediaType null' => ['mediaType', null];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideSubclassesImplementingSleep')]
    public function testSerializeSubclassImplementingSleep(string $class)
    {
        $part = (new $class('content', 'state'))->setContentId('foo@example.com');
        $part->getHeaders()->addTextHeader('X-Foo', 'bar');

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/mime 7.4: Passing extra keys to "%s::__unserialize()" is deprecated, populate properties in "%s::__unserialize()" instead.', DataPart::class, $class));

        $unserialized = unserialize(serialize($part));

        $this->assertSame('state', $unserialized->getState());
        $this->assertSame('foo@example.com', $unserialized->getContentId());
        $this->assertSame($part->toString(), $unserialized->toString());
    }

    public static function provideSubclassesImplementingSleep(): iterable
    {
        yield [DataPartTestSleepPart::class];
        yield [DataPartTestWakeupPart::class];
    }
}

class DataPartTestSleepPart extends DataPart
{
    protected ?string $state = null;

    public function __construct(string $body, string $state)
    {
        parent::__construct($body, 'file.txt', 'text/plain');

        $this->state = $state;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function __sleep(): array
    {
        return [...parent::__sleep(), 'state'];
    }
}

class DataPartTestWakeupPart extends DataPartTestSleepPart
{
    public function __wakeup(): void
    {
        parent::__wakeup();
    }
}

class DataPartTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}
