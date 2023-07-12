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

use Symfony\Component\VarExporter\VarExporter;

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
    public function write(string $path, string $locale, $data)
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

        file_put_contents($path.'/'.$locale.'.php', sprintf($template, VarExporter::export($data)));
    }
}
