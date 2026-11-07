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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;

class FileLoaderTest extends TestCase
{
    public function testBaseFileLoaderCapturesBeforeTheProtectedHook()
    {
        $path = tempnam(sys_get_temp_dir(), 'sf-translation-capture-');
        file_put_contents($path, 'key: v1');
        $loader = new class extends YamlFileLoader {
            /**
             * Saves changed YAML after the actual loader has parsed its previous contents.
             *
             * @return array{key: string}
             */
            protected function loadResource(string $resource): array
            {
                $messages = parent::loadResource($resource);
                file_put_contents($resource, 'key: v2');

                return $messages;
            }
        };
        try {
            $catalogue = $loader->load($path, 'en');
            self::assertSame('v1', $catalogue->get('key'));
            self::assertFalse($catalogue->getResources()[0]->isFresh(time() + 10));
        } finally {
            unlink($path);
        }
    }
}
