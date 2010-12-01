<?php

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Translation\MessageCatalogue;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ArrayLoader loads translations from a PHP array.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ArrayLoader implements LoaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function load($resource, $locale, $domain = 'messages')
    {
        $this->flatten($resource);
        $catalogue = new MessageCatalogue($locale);
        $catalogue->add($resource, $domain);

        return $catalogue;
    }

    /**
     * Flattens an nested array of translations
     *
     * The scheme used is:
     *   'key' => array('key2' => array('key3' => 'value'))
     * Becomes:
     *   'key.key2.key3' => 'value'
     *
     * This function takes an array by reference and will modify it
     *
     * @param array $messages the array that will be flattened
     * @param array $subnode current subnode being parsed, used internally for recursive calls
     * @param string $path current path being parsed, used internally for recursive calls
     */
    protected function flatten(array &$messages, array $subnode = null, $path = null)
    {
        if ($subnode === null) {
            $subnode =& $messages;
        }
        foreach ($subnode as $key => $value) {
            if (is_array($value)) {
                $nodePath = $path ? $path.'.'.$key : $key;
                $this->flatten($messages, $value, $nodePath);
                if ($path === null) {
                    unset($messages[$key]);
                }
            } elseif ($path !== null) {
                $messages[$path.'.'.$key] = $value;
            }
        }
    }
}
