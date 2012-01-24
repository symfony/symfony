<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;

/**
 * ResourceBundleLoader loads translations from a resource bundle.
 *
 * @author stealth35
 */
class ResourceBundleLoader implements LoaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function load($resource, $locale, $domain = 'messages')
    {
        $rb = new \ResourceBundle($locale, $resource);

        if (!$rb) {
            throw new \RuntimeException("cannot load this resource : $resource");
        } elseif (intl_is_failure($rb->getErrorCode())) {
            throw new \RuntimeException($rb->getErrorMessage(), $rb->getErrorCode());
        }

        $messages = $this->flatten($rb);
        $catalogue = new MessageCatalogue($locale);
        $catalogue->add($messages, $domain);

        if (is_dir($resource)) {
            $catalogue->addResource(new DirectoryResource($resource));
        } elseif (is_file($resource.'.dat')) {
            $catalogue->addResource(new FileResource($resource.'.dat'));
        }

        return $catalogue;
    }

    /**
     * Flattens an ResourceBundle
     *
     * The scheme used is:
     *   key { key2 { key3 { "value" } } }
     * Becomes:
     *   'key.key2.key3' => 'value'
     *
     * This function takes an array by reference and will modify it
     *
     * @param \ResourceBundle $rb        the ResourceBundle that will be flattened
     * @param array           &$messages used internally for recursive calls
     * @param string          $path      current path being parsed, used internally for recursive calls
     *
     * @return array the flattened ResourceBundle
     */
    private function flatten(\ResourceBundle $rb, array &$messages = array(), $path = null)
    {
        foreach ($rb as $key => $value) {
            $nodePath = $path ? $path.'.'.$key : $key;
            if ($value instanceof \ResourceBundle) {
                $this->flatten($value, $messages, $nodePath);
            } else {
                $messages[$nodePath] = $value;
            }
        }

        return $messages;
    }
}
