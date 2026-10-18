<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;
use Symfony\Component\VarDumper\Tests\Fixtures\MessengerHandlerFixture;

final class SymfonyCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testCastUuid()
    {
        $uuid = new UuidV4('83a9db35-3c8c-4040-b3c1-02eccc00b419');
        $expectedDump = <<<EODUMP
            Symfony\Component\Uid\UuidV4 {
              #uid: "83a9db35-3c8c-4040-b3c1-02eccc00b419"
              toBase58: "HFzAAuYvev42cCjwqpnKqz"
              toBase32: "43N7DKAF4C810B7G82XK601D0S"
            }
            EODUMP;
        $this->assertDumpEquals($expectedDump, $uuid);

        $uuid = new UuidV6('1ebc50e9-8a23-6704-ad6f-59afd5cda7e5');
        $expectedDump = <<<EODUMP
            Symfony\Component\Uid\UuidV6 {
              #uid: "1ebc50e9-8a23-6704-ad6f-59afd5cda7e5"
              toBase58: "4o8c5m6v4L8h5teww36JDa"
              toBase32: "0YQH8EK2H3CW2ATVTSNZAWV9Z5"
              time: "2021-06-04 08:26:44.591386 UTC"
            }
            EODUMP;

        $this->assertDumpEquals($expectedDump, $uuid);
    }

    public function testCastUlid()
    {
        $ulid = new Ulid('01F7B252SZQGTSQGYSGACASAW6');
        $expectedDump = <<<EODUMP
            Symfony\Component\Uid\Ulid {
              #uid: "01F7B252SZQGTSQGYSGACASAW6"
              toBase58: "1Ba6pJPFWDwghSKFVvfQ1B"
              toRfc4122: "0179d622-8b3f-bc35-9bc3-d98298acab86"
              time: "2021-06-04 08:27:38.687 UTC"
            }
            EODUMP;

        $this->assertDumpEquals($expectedDump, $ulid);
    }

    public function testCastMessengerStamps()
    {
        $handler = new class extends MessengerHandlerFixture {
        };
        $envelope = new Envelope(new \stdClass(), [
            new HandledStamp(null, $handler::class.'::__invoke@my_alias'),
            new HandledStamp(null, 'Closure'),
            new HandledStamp(null, 'App\NotExisting::__invoke'),
            new SentStamp(MessengerHandlerFixture::class, 'async'),
        ]);

        $expectedDump = <<<'EODUMP'
            Symfony\Component\Messenger\Envelope {
              -stamps: array:2 [
                "Symfony\Component\Messenger\Stamp\HandledStamp" => array:3 [
                  0 => Symfony\Component\Messenger\Stamp\HandledStamp {
                    -result: null
                    -handlerName: "Symfony\Component\VarDumper\Tests\Fixtures\MessengerHandlerFixture@anonymous::__invoke@my_alias"
                  }
                  1 => Symfony\Component\Messenger\Stamp\HandledStamp {
                    -result: null
                    -handlerName: "Closure"
                  }
                  2 => Symfony\Component\Messenger\Stamp\HandledStamp {
                    -result: null
                    -handlerName: "App\NotExisting::__invoke"
                  }
                ]
                "Symfony\Component\Messenger\Stamp\SentStamp" => array:1 [
                  0 => Symfony\Component\Messenger\Stamp\SentStamp {
                    -senderClass: "Symfony\Component\VarDumper\Tests\Fixtures\MessengerHandlerFixture"
                    -senderAlias: "async"
                  }
                ]
              ]
              -message: {}
            }
            EODUMP;

        $this->assertDumpEquals($expectedDump, $envelope);
    }

    #[DataProvider('provideMessengerObjects')]
    public function testMessengerCastersLinkToSource(object $object, int $line, string $tail)
    {
        $dumper = new HtmlDumper();
        $dumper->setDumpHeader('');
        $dumper->setDisplayOptions(['fileLinkFormat' => '%f:%l']);
        $dump = $dumper->dump((new VarCloner())->cloneVar($object), true);

        $this->assertStringContainsString('<a href="'.(new \ReflectionClass(MessengerHandlerFixture::class))->getFileName().':'.$line.'"', $dump);
        $this->assertStringContainsString('<span class="sf-dump-ellipsis-tail">'.$tail.'</span>', $dump);
    }

    public static function provideMessengerObjects(): iterable
    {
        $class = new \ReflectionClass(MessengerHandlerFixture::class);
        $method = $class->getMethod('__invoke');

        yield 'HandledStamp' => [new HandledStamp(null, $class->name.'::__invoke'), $method->getStartLine(), 'MessengerHandlerFixture::__invoke'];
        yield 'HandledStamp with alias' => [new HandledStamp(null, $class->name.'::__invoke@my_alias'), $method->getStartLine(), 'MessengerHandlerFixture::__invoke@my_alias'];
        yield 'HandledStamp with alias containing ::' => [new HandledStamp(null, $class->name.'::__invoke@app.handler::foo'), $method->getStartLine(), 'MessengerHandlerFixture::__invoke@app.handler::foo'];
        yield 'SentStamp' => [new SentStamp($class->name), $class->getStartLine(), 'MessengerHandlerFixture'];
        yield 'ErrorDetailsStamp' => [new ErrorDetailsStamp($class->name, 0, 'boom'), $class->getStartLine(), 'MessengerHandlerFixture'];
    }
}
