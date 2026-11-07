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

use Symfony\Component\Routing\Loader\DirectoryLoader;
use Symfony\Component\Routing\RouteCollection;

class EqualTimestampDirectoryLoader extends DirectoryLoader
{
    /**
     * Starts directory consumption near a second boundary so the unchanged input and normal route dump share that second.
     */
    public function load(mixed $file, ?string $type = null): RouteCollection
    {
        $marker = Mutation::$directory.'/equal-timestamp';
        if (!is_file($marker)) {
            while (microtime(true) - floor(microtime(true)) > 0.05) {
                usleep(1000);
            }
            $path = $this->locator->locate($file);
            touch($path.'/dynamic.yml', $mtime = time());
            touch($path, $mtime);
            clearstatcache();
            file_put_contents($marker, (string) $mtime);
        }

        return parent::load($file, $type);
    }
}
