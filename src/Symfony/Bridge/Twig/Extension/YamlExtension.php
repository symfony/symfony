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
 *
 * @since v2.0.0
 */
class YamlExtension extends \Twig_Extension
{
    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('yaml_encode', array($this, 'encode')),
            new \Twig_SimpleFilter('yaml_dump', array($this, 'dump')),
        );
    }

    /**
     * @since v2.0.22
     */
    public function encode($input, $inline = 0, $dumpObjects = false)
    {
        static $dumper;

        if (null === $dumper) {
            $dumper = new YamlDumper();
        }

        return $dumper->dump($input, $inline, false, $dumpObjects);
    }

    /**
     * @since v2.0.22
     */
    public function dump($value, $inline = 0, $dumpObjects = false)
    {
        if (is_resource($value)) {
            return '%Resource%';
        }

        if (is_array($value) || is_object($value)) {
            return '%'.gettype($value).'% '.$this->encode($value, $inline, $dumpObjects);
        }

        return $this->encode($value, $inline, $dumpObjects);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     *
     * @since v2.0.0
     */
    public function getName()
    {
        return 'yaml';
    }
}
