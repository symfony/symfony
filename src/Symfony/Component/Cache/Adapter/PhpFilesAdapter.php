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


class PhpFilesAdapter extends AbstractFilesystemAdapter
{
    public function __construct($namespace = '', $defaultLifetime = 0, $directory = null)
    {
        parent::__construct('', $defaultLifetime, $directory, '.php');
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        $values = array();

        foreach ($ids as $id) {
            $filePath = $this->getFile($id);
            $valueArray = $this->includeCacheFileForId($filePath);
            if (null === $valueArray) {
                @unlink($filePath);
            } else {
                $values[$id] = $valueArray[1];
            }
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
    protected function doSave(array $values, $lifetime)
    {
        $ok = true;
        $expiresAt = $lifetime ? time() + $lifetime : PHP_INT_MAX;

        foreach ($values as $id => $value) {
            $fileContent = $this->getCacheFileContent($id, $value, $expiresAt);
            $ok = $this->saveFile($id, $fileContent) && $ok;
        }

        return $ok;
    }

    /**
     * @param string $filePath
     *
     * @return mixed|null
     */
    private function includeCacheFileForId($filePath)
    {
        $valueArray = @include $filePath;

        if (!is_array($valueArray) || 2 !== count($valueArray)) {
            return null;
        }

        list($expiresAt, $value) = $valueArray;
        if (time() >= (int) $expiresAt) {
            return null;
        }

        return $valueArray;
    }

    /**
     * @inheritdoc
     */
    private function getCacheFileContent($id, $value, $expiresAt)
    {
        $exportedValue = var_export(serialize(array($expiresAt, $value)), true);
        return '<?php return unserialize('.$exportedValue.');';
    }
}
