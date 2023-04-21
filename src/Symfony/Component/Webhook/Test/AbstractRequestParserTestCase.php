<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\RequestParserInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
abstract class AbstractRequestParserTestCase extends TestCase
{
    /**
     * @dataProvider getPayloads
     */
    public function testParse(string $payload, RemoteEvent $expected)
    {
        $request = $this->createRequest($payload);
        $parser = $this->createRequestParser();
        $wh = $parser->parse($request, $this->getSecret());
        $this->assertEquals($expected, $wh);
    }

    public static function getPayloads(): iterable
    {
        $currentDir = \dirname((new \ReflectionClass(static::class))->getFileName());
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($currentDir.'/Fixtures', \RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $filename = str_replace($currentDir.'/Fixtures/', '', $file->getPathname());
            if (static::getFixtureExtension() !== pathinfo($filename, \PATHINFO_EXTENSION)) {
                continue;
            }

            yield $filename => [
                file_get_contents($file),
                include(str_replace('.'.static::getFixtureExtension(), '.php', $file->getPathname())),
            ];
        }
    }

    abstract protected function createRequestParser(): RequestParserInterface;

    protected function getSecret(): string
    {
        return '';
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
        ], $payload);
    }

    protected static function getFixtureExtension(): string
    {
        return 'json';
    }
}
