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
use Symfony\Component\Config\Resource\FileResource;

/**
 * ResourceBundleLoader loads translations from a resource bundle.
 *
 * @author stealth35
 *
 * @api
 */
class ResourceBundleLoader implements LoaderInterface
{
    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function load($resource, $locale, $domain = 'messages')
    {
        $ressource = new \ResourceBundle($locale, $resource);

        if (!$ressource) {
            throw new \RuntimeException("cannot load this ressource : $resource");
        } elseif (intl_is_failure($ressource->getErrorCode())) {
            throw new \RuntimeException($ressource->getErrorMessage(), $ressource->getErrorCode());
        }

        $messages = $this->flatten($ressource);
        $catalogue = new MessageCatalogue($locale);
        $catalogue->add($messages, $domain);
        $catalogue->addResource(new FileResource($resource));

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
     * @param array \ResourceBundle the ResourceBundle that will be flattened
     * @param array $messages used internally for recursive calls
     * @param string $path current path being parsed, used internally for recursive calls
     *
     * @return array the flattened ResourceBundle
     */
    private function flatten(\ResourceBundle $ressource, array &$messages = array(), $path = null)
    {
        foreach ($ressource as $key => $value) {
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
