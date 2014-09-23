<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Writer;

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
        $template = <<<TEMPLATE
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return %s;

TEMPLATE;

        $data = var_export($data, true);
        $data = preg_replace('/array \(/', 'array(', $data);
        $data = preg_replace('/\n {1,10}array\(/', 'array(', $data);
        $data = preg_replace('/  /', '    ', $data);
        $data = sprintf($template, $data);

        file_put_contents($path.'/'.$locale.'.php', $data);
    }
}
