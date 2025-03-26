<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonStreamer\Dto\Dummy;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\JsonStreamer\StreamReaderInterface;
use Symfony\Component\JsonStreamer\StreamWriterInterface;
use Symfony\Component\TypeInfo\Type;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class JsonStreamerTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        static::bootKernel(['test_case' => 'JsonStreamer']);
    }

    public function testWrite()
    {
        /** @var StreamWriterInterface $writer */
        $writer = static::getContainer()->get('json_streamer.stream_writer.alias');

        $this->assertSame('{"@name":"DUMMY","range":"10..20"}', (string) $writer->write(new Dummy(), Type::object(Dummy::class)));
    }

    public function testRead()
    {
        /** @var StreamReaderInterface $reader */
        $reader = static::getContainer()->get('json_streamer.stream_reader.alias');

        $expected = new Dummy();
        $expected->name = 'dummy';
        $expected->range = [0, 1];

        $this->assertEquals($expected, $reader->read('{"@name": "DUMMY", "range": "0..1"}', Type::object(Dummy::class)));
    }

    public function testWarmupStreamableClasses()
    {
        /** @var Filesystem $fs */
        $fs = static::getContainer()->get('filesystem');

        $streamWritersDir = \sprintf('%s/json_streamer/stream_writer/', static::getContainer()->getParameter('kernel.cache_dir'));

        // clear already created stream writers
        if ($fs->exists($streamWritersDir)) {
            $fs->remove($streamWritersDir);
        }

        static::getContainer()->get('json_streamer.cache_warmer.streamer.alias')->warmUp(static::getContainer()->getParameter('kernel.cache_dir'));

        $this->assertFileExists($streamWritersDir);
        $this->assertCount(2, glob($streamWritersDir.'/*'));
    }
}
