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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\IcuDatFileLoader;

#[RequiresPhpExtension('intl')]
class IcuDatFileLoaderTest extends LocalizedTestCase
{
    public function testLoadInvalidResource()
    {
        $this->expectException(InvalidResourceException::class);

        (new IcuDatFileLoader())->load(__DIR__.'/../Fixtures/resourcebundle/corrupted/resources', 'es', 'domain2');
    }

    public function testDatEnglishLoad()
    {
        // bundled resource is build using pkgdata command which at least in ICU 4.2 comes in extremely! buggy form
        // you must specify an temporary build directory which is not the same as current directory and
        // MUST reside on the same partition. pkgdata -p resources -T /srv -d.packagelist.txt
        $loader = new IcuDatFileLoader();
        $resource = __DIR__.'/../Fixtures/resourcebundle/dat/resources';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(['symfony' => 'Symfony 2 is great'], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource.'.dat')], $catalogue->getResources());
    }

    public function testDatFrenchLoad()
    {
        $loader = new IcuDatFileLoader();
        $resource = __DIR__.'/../Fixtures/resourcebundle/dat/resources';
        $catalogue = $loader->load($resource, 'fr', 'domain1');

        $this->assertEquals(['symfony' => 'Symfony 2 est génial'], $catalogue->all('domain1'));
        $this->assertEquals('fr', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource.'.dat')], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);

        (new IcuDatFileLoader())->load(__DIR__.'/../Fixtures/non-existing.txt', 'en', 'domain1');
    }

    public function testIcuCapturesBeforeFlattening()
    {
        if (!class_exists(\ResourceBundle::class)) {
            self::markTestSkipped('The intl extension is required.');
        }
        $directory = sys_get_temp_dir().'/sf-icu-capture-'.bin2hex(random_bytes(6));
        mkdir($directory);
        copy(__DIR__.'/../Fixtures/resourcebundle/dat/resources.dat', $directory.'/resources.dat');
        $loader = new class extends IcuDatFileLoader {
            public string $path;

            /**
             * Changes the bundle bytes while its previous messages are being flattened.
             *
             * @param array<string, string> $messages
             *
             * @return array<string, string>
             */
            protected function flatten(\ResourceBundle $rb, array &$messages = [], ?string $path = null): array
            {
                $this->changeInput();

                return parent::flatten($rb, $messages, $path);
            }

            private function changeInput(): void
            {
                file_put_contents($this->path, str_replace("g\0r\0e\0a\0t\0", "s\0u\0p\0e\0r\0", file_get_contents($this->path)));
                clearstatcache();
            }
        };
        $resource = $directory.'/resources';
        $loader->path = $resource.'.dat';
        try {
            $catalogue = $loader->load($resource, 'en');
            self::assertNotEmpty($catalogue->all());
            self::assertFalse($catalogue->getResources()[0]->isFresh(time() + 10));
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
