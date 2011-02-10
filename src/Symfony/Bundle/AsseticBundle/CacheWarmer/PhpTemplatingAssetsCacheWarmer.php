<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\CacheWarmer;

use Symfony\Bundle\AsseticBundle\Templating\FormulaLoader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;
use Symfony\Component\HttpKernel\Kernel;

class PhpTemplatingAssetsCacheWarmer extends CacheWarmer
{
    protected $kernel;
    protected $loader;

    public function __construct(Kernel $kernel, FormulaLoader $loader)
    {
        $this->kernel = $kernel;
        $this->loader = $loader;
    }

    public function warmUp($cacheDir)
    {
        $formulae = array();
        foreach ($this->kernel->getBundles() as $name => $bundle) {
            if (is_dir($dir = $bundle->getPath().'/Resources/views/')) {
                $finder = new Finder();
                $finder->files()->name('*.php')->in($dir);
                foreach ($finder as $file) {
                    $formulae += $this->loader->load($name.':'.substr($file->getPath(), strlen($dir)).':'.$file->getBasename());
                }
            }
        }

        if (is_dir($dir = $this->kernel->getRootDir().'/views/')) {
            $finder = new Finder();
            $finder->files()->name('*.php')->in($dir);
            foreach ($finder as $file) {
                $formulae += $this->loader->load(':'.substr($file->getPath(), strlen($dir)).':'.$file->getBasename());
            }
        }

        $this->writeCacheFile($cacheDir.'/assetic_php_templating_assets.php', '<?php return '.var_export($formulae, true).';');
    }

    public function isOptional()
    {
        return false;
    }
}
