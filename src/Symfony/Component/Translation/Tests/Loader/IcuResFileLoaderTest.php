<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\IcuResFileLoader;

#[RequiresPhpExtension('intl')]
class IcuResFileLoaderTest extends LocalizedTestCase
{
    public function testLoad()
    {
        // resource is build using genrb command
        $loader = new IcuResFileLoader();
        $resource = __DIR__.'/../Fixtures/resourcebundle/res';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar'], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $resources = $catalogue->getResources();
        $this->assertCount(1, $resources);
        $this->assertInstanceOf(DirectoryResource::class, $resources[0]);
        $this->assertSame(realpath($resource), $resources[0]->getResource());
        $this->assertNull($resources[0]->getPattern());
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);

        (new IcuResFileLoader())->load(__DIR__.'/../Fixtures/non-existing.txt', 'en', 'domain1');
    }

    public function testLoadInvalidResource()
    {
        $this->expectException(InvalidResourceException::class);

        (new IcuResFileLoader())->load(__DIR__.'/../Fixtures/resourcebundle/corrupted', 'en', 'domain1');
    }

    #[RequiresFunction('proc_open')]
    public function testIcuCapturesBeforeFlattening()
    {
        $directory = sys_get_temp_dir().'/sf-icu-capture-'.bin2hex(random_bytes(6));
        mkdir($directory);
        copy(__DIR__.'/../Fixtures/resourcebundle/res/en.res', $directory.'/en.res');

        // ICU keeps the bundle mapped on Windows until the process exits, so the parent removes it after the child exits.
        $code = <<<'PHP'
            require $argv[1];

            $loader = new class extends Symfony\Component\Translation\Loader\IcuResFileLoader {
                public string $directory;

                /**
                 * Adds a file while the real ICU bundle is being flattened.
                 *
                 * @param array<string, string> $messages
                 *
                 * @return array<string, string>
                 */
                protected function flatten(\ResourceBundle $rb, array &$messages = [], ?string $path = null): array
                {
                    file_put_contents($this->directory.'/added', 'new');

                    return parent::flatten($rb, $messages, $path);
                }
            };
            $loader->directory = $argv[2];
            $catalogue = $loader->load($argv[2], 'en');
            echo json_encode(['messages' => $catalogue->all('messages'), 'fresh' => $catalogue->getResources()[0]->isFresh(time() + 10)], JSON_THROW_ON_ERROR);
            PHP;
        try {
            $autoload = null;
            foreach (ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
                if ($loader->findFile(IcuResFileLoader::class)) {
                    $autoload = $vendor.'/autoload.php';
                    break;
                }
            }
            self::assertNotNull($autoload);
            $process = proc_open([\PHP_BINARY, '-r', $code, $autoload, $directory], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $error);
            $result = json_decode($output, true, flags: \JSON_THROW_ON_ERROR);
            self::assertSame(['foo' => 'bar'], $result['messages']);
            self::assertFalse($result['fresh']);
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
