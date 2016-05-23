<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Adapter\Helper\FilesCacheHelper;

class PhpFilesAdapter extends AbstractAdapter
{
    /**
     * @var FilesCacheHelper
     */
    protected $filesCacheHelper;

    /**
     * @param string $namespace       Cache namespace
     * @param int    $defaultLifetime Default lifetime for cache items
     * @param null   $directory       Path where cache items should be stored, defaults to sys_get_temp_dir().'/symfony-cache'
     * @param string $version         Version (works the same way as namespace)
     */
    public function __construct($namespace = '', $defaultLifetime = 0, $directory = null, $version = null)
    {
        parent::__construct($namespace, $defaultLifetime);
        $this->filesCacheHelper = new FilesCacheHelper($directory, $namespace, $version, '.php');
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        $values = array();

        foreach ($ids as $id) {
            $valueArray = $this->includeCacheFile($this->filesCacheHelper->getFilePath($id));
            if (!is_array($valueArray)) {
                continue;
            }
            $values[$id] = $valueArray[0];
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        return 0 !== count($this->doFetch(array($id)));
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        $directory = $this->filesCacheHelper->getDirectory();

        return !(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS))->valid();
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        foreach ($ids as $id) {
            $file = $this->filesCacheHelper->getFilePath($id);
            if (@file_exists($file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        $ok = true;
        $expiresAt = $lifetime ? time() + $lifetime : PHP_INT_MAX;

        foreach ($values as $id => $value) {
            $file = $this->filesCacheHelper->getFilePath($id, true);
            if (file_exists($file)) {
                $ok = false;
            } else {
                $ok = $this->saveCacheFile($file, $value, $expiresAt) && $ok;
            }
        }

        return $ok;
    }

    /**
     * @param string $file
     * @param mixed  $value
     * @param int    $expiresAt
     *
     * @return bool
     */
    private function saveCacheFile($file, $value, $expiresAt)
    {
        $fileContent = $this->createCacheFileContent($value, $expiresAt);

        return $this->filesCacheHelper->saveFile($file, $fileContent);
    }

    /**
     * @param string $file File path
     *
     * @return array|null unserialized value wrapped in array or null
     */
    private function includeCacheFile($file)
    {
        $valueArray = @include $file;
        if (!is_array($valueArray) || 2 !== count($valueArray)) {
            return;
        }

        list($serializedValue, $expiresAt) = $valueArray;
        if (time() > (int) $expiresAt) {
            return;
        }

        $unserializedValueInArray = unserialize($serializedValue);
        if (!is_array($unserializedValueInArray)) {
            return;
        }

        return $unserializedValueInArray;
    }

    /**
     * @param mixed $value
     * @param int   $expiresAt
     *
     * @return string
     */
    private function createCacheFileContent($value, $expiresAt)
    {
        $exportedValue = var_export(array(serialize([$value]), $expiresAt), true);

        return '<?php return '.$exportedValue.';';
    }
}
