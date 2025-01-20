<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Iterator;

trait VfsIteratorTestTrait
{
    private static int $vfsNextSchemeIndex = 0;

    /** @var array<string, \Closure(string, 'list_dir_open'|'list_dir_rewind'|'is_dir'): (list<string>|bool)> */
    public static array $vfsProviders;

    protected string $vfsScheme;

    /** @var list<array{string, string, mixed}> */
    protected array $vfsLog = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->vfsScheme = 'symfony-finder-vfs-test-'.++self::$vfsNextSchemeIndex;

        $vfsWrapperClass = \get_class(new class {
            /** @var array<string, \Closure(string, 'list_dir_open'|'list_dir_rewind'|'is_dir'): (list<string>|bool)> */
            public static array $vfsProviders = [];

            /** @var resource */
            public $context;

            private string $scheme;

            private string $dirPath;

            /** @var list<string> */
            private array $dirData;

            private function parsePathAndSetScheme(string $url): string
            {
                $urlArr = parse_url($url);
                \assert(\is_array($urlArr));
                \assert(isset($urlArr['scheme']));
                \assert(isset($urlArr['host']));

                $this->scheme = $urlArr['scheme'];

                return str_replace(\DIRECTORY_SEPARATOR, '/', $urlArr['host'].($urlArr['path'] ?? ''));
            }

            public function processListDir(bool $fromRewind): bool
            {
                $providerFx = self::$vfsProviders[$this->scheme];
                $data = $providerFx($this->dirPath, 'list_dir'.($fromRewind ? '_rewind' : '_open'));
                \assert(\is_array($data));
                $this->dirData = $data;

                return true;
            }

            public function dir_opendir(string $url): bool
            {
                $this->dirPath = $this->parsePathAndSetScheme($url);

                return $this->processListDir(false);
            }

            public function dir_readdir(): string|false
            {
                return array_shift($this->dirData) ?? false;
            }

            public function dir_closedir(): bool
            {
                unset($this->dirPath);
                unset($this->dirData);

                return true;
            }

            public function dir_rewinddir(): bool
            {
                return $this->processListDir(true);
            }

            /**
             * @return array<string, mixed>
             */
            public function stream_stat(): array
            {
                return [];
            }

            /**
             * @return array<string, mixed>
             */
            public function url_stat(string $url): array
            {
                $path = $this->parsePathAndSetScheme($url);
                $providerFx = self::$vfsProviders[$this->scheme];
                $isDir = $providerFx($path, 'is_dir');
                \assert(\is_bool($isDir));

                return ['mode' => $isDir ? 0040755 : 0100644];
            }
        });
        self::$vfsProviders = &$vfsWrapperClass::$vfsProviders;

        stream_wrapper_register($this->vfsScheme, $vfsWrapperClass);
    }

    protected function tearDown(): void
    {
        stream_wrapper_unregister($this->vfsScheme);

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function setupVfsProvider(array $data): void
    {
        self::$vfsProviders[$this->vfsScheme] = function (string $path, string $op) use ($data) {
            $pathArr = explode('/', $path);
            $fileEntry = $data;
            while (($name = array_shift($pathArr)) !== null) {
                if (!isset($fileEntry[$name])) {
                    $fileEntry = false;

                    break;
                }

                $fileEntry = $fileEntry[$name];
            }

            if ('list_dir_open' === $op || 'list_dir_rewind' === $op) {
                /** @var list<string> $res */
                $res = array_keys($fileEntry);
            } elseif ('is_dir' === $op) {
                $res = \is_array($fileEntry);
            } else {
                throw new \Exception('Unexpected operation type');
            }

            $this->vfsLog[] = [$path, $op, $res];

            return $res;
        };
    }

    protected function stripSchemeFromVfsPath(string $url): string
    {
        $urlArr = parse_url($url);
        \assert(\is_array($urlArr));
        \assert($urlArr['scheme'] === $this->vfsScheme);
        \assert(isset($urlArr['host']));

        return str_replace(\DIRECTORY_SEPARATOR, '/', $urlArr['host'].($urlArr['path'] ?? ''));
    }

    protected function assertSameVfsIterator(array $expected, \Traversable $iterator)
    {
        $values = array_map(fn (\SplFileInfo $fileinfo) => $this->stripSchemeFromVfsPath($fileinfo->getPathname()), iterator_to_array($iterator));

        $this->assertEquals($expected, array_values($values));
    }
}
