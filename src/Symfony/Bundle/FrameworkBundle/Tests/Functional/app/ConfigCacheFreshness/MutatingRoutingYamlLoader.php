<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness;

use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Parser;

class MutatingRoutingYamlLoader extends YamlFileLoader
{
    /**
     * Injects an edit after the production YAML parser has returned its previous route data.
     */
    public function load(mixed $file, ?string $type = null): RouteCollection
    {
        if ('route-checker' === Mutation::$mode) {
            Mutation::change($file);
        }
        $parser = new class extends Parser {
            public function parseFile(string $filename, int $flags = 0): mixed
            {
                $result = parent::parseFile($filename, $flags);
                if (str_contains(file_get_contents($filename), '/v1')) {
                    if ('route-directory-unchanged' === Mutation::$mode || 'route-directory-equal' === Mutation::$mode) {
                        return $result;
                    }
                    if ('route-directory-remove' === Mutation::$mode && !is_file(Mutation::$directory.'/changed')) {
                        unlink($filename);
                        file_put_contents(Mutation::$directory.'/changed', $filename);
                        clearstatcache();
                    } elseif (str_starts_with(Mutation::$mode, 'route-directory-add') && !is_file(Mutation::$directory.'/changed')) {
                        $path = \dirname($filename).'/z-added.yml';
                        file_put_contents($path, 'dynamic: { path: /v2 }');
                        touch($path, time() - 1);
                        touch(\dirname($filename), time() - 1);
                        file_put_contents(Mutation::$directory.'/changed', $path);
                        clearstatcache();
                    } else {
                        Mutation::change($filename);
                    }
                }

                return $result;
            }
        };
        (new \ReflectionProperty(YamlFileLoader::class, 'yamlParser'))->setValue($this, $parser);

        return parent::load($file, $type);
    }
}
