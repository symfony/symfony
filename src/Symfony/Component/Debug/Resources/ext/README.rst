Symfony Debug Extension
=======================

This extension adds a ``symfony_zval_info($key, $array, $options = 0)`` function that:

- exposes zval_hash/refcounts, allowing e.g. efficient exploration of arbitrary structures in PHP,
- does work with references, preventing memory copying.

Its behavior is about the same as:

.. code-block:: php

    <?php

    function symfony_zval_info($key, $array, $options = 0)
    {
        // $options is currently not used, but could be in future version.

        if (!array_key_exists($key, $array)) {
            return null;
        }

        $info = array(
            'type' => gettype($array[$key]),
            'zval_hash' => /* hashed memory address of $array[$key] */,
            'zval_refcount' => /* internal zval refcount of $array[$key] */,
            'zval_isref' => /* is_ref status of $array[$key] */,
        );

        switch ($info['type']) {
            case 'object':
                $info += array(
                    'object_class' => get_class($array[$key]),
                    'object_refcount' => /* internal object refcount of $array[$key] */,
                    'object_hash' => spl_object_hash($array[$key]),
                    'object_handle' => /* internal object handle $array[$key] */,
                );
                break;

            case 'resource':
                $info += array(
                    'resource_handle' => (int) $array[$key],
                    'resource_type' => get_resource_type($array[$key]),
                    'resource_refcount' => /* internal resource refcount of $array[$key] */,
                );
                break;

            case 'array':
                $info += array(
                    'array_count' => count($array[$key]),
                );
                break;

            case 'string':
                $info += array(
                    'strlen' => strlen($array[$key]),
                );
                break;
        }

        return $info;
    }

To enable the extension from source, run:

.. code-block:: sh

    phpize
    ./configure
    make
    sudo make install

