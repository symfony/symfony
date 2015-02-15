<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Bundle\Writer;

/**
 * Writes .json resource bundles.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class JsonBundleWriter implements BundleWriterInterface
{
    /**
     * {@inheritdoc}
     */
    public function write($path, $locale, $data)
    {
        if ($data instanceof \Traversable) {
            $data = iterator_to_array($data);
        }

        array_walk_recursive($data, function (&$value) {
            if ($value instanceof \Traversable) {
                $value = iterator_to_array($value);
            }
        });

<<<<<<< HEAD
        if (PHP_VERSION_ID >= 50400) {
            // Use JSON_PRETTY_PRINT so that we can see what changed in Git diffs
            file_put_contents(
                $path.'/'.$locale.'.json',
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
            );
        } else {
            file_put_contents($path.'/'.$locale.'.json', json_encode($data)."\n");
        }
=======
        $contents = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

        file_put_contents($path.'/'.$locale.'.json', $contents);
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
    }
}
