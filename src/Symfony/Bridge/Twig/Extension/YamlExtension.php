<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Yaml\Dumper as YamlDumper;

/**
 * Provides integration of the Yaml component with Twig.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class YamlExtension extends \Twig_Extension
{
    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            'yaml_encode' => new \Twig_Filter_Method($this, 'encode'),
            'yaml_dump'   => new \Twig_Filter_Method($this, 'dump'),
        );
    }

    public function encode($input, $inline = 0)
    {
        static $dumper;

        if (null === $dumper) {
            $dumper = new YamlDumper();
        }

        return $dumper->dump($input, $inline);
    }

    public function dump($value)
    {
        if (is_resource($value)) {
            return '%Resource%';
        }

        if (is_array($value) || is_object($value)) {
            return '%'.gettype($value).'% '.$this->encode($value);
        }

        return $value;
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'yaml';
    }
}
