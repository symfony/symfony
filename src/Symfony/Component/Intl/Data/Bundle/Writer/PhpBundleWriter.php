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
 * Writes .php resource bundles.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class PhpBundleWriter implements BundleWriterInterface
{
    /**
     * {@inheritdoc}
     */
    public function write($path, $locale, $data)
    {
        $template = <<<'TEMPLATE'
<?php

return %s;

TEMPLATE;

        if ($data instanceof \Traversable) {
            $data = iterator_to_array($data);
        }

        array_walk_recursive($data, function (&$value) {
            if ($value instanceof \Traversable) {
                $value = iterator_to_array($value);
            }
        });

        $data = var_export($data, true);
        $data = preg_replace('/array \(/', '[', $data);
        $data = preg_replace('/\n {1,10}\[/', '[', $data);
        $data = preg_replace('/  /', '    ', $data);
        $data = preg_replace('/\),$/m', '],', $data);
        $data = preg_replace('/\)$/', ']', $data);
        $data = sprintf($template, $data);

        file_put_contents($path.'/'.$locale.'.php', $data);
    }
}
