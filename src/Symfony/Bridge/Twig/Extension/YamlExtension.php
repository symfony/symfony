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
use Symfony\Component\Yaml\Yaml;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides integration of the Yaml component with Twig.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class YamlExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('yaml_encode', [$this, 'encode']),
            new TwigFilter('yaml_dump', [$this, 'dump']),
        ];
    }

    public function encode($input, $inline = 0, $dumpObjects = 0)
    {
        static $dumper;

        if (null === $dumper) {
            $dumper = new YamlDumper();
        }

        if (\defined('Symfony\Component\Yaml\Yaml::DUMP_OBJECT')) {
            if (\is_bool($dumpObjects)) {
                @trigger_error('Passing a boolean flag to toggle object support is deprecated since Symfony 3.1 and will be removed in 4.0. Use the Yaml::DUMP_OBJECT flag instead.', E_USER_DEPRECATED);

                $flags = $dumpObjects ? Yaml::DUMP_OBJECT : 0;
            } else {
                $flags = $dumpObjects;
            }

            return $dumper->dump($input, $inline, 0, $flags);
        }

        return $dumper->dump($input, $inline, 0, false, $dumpObjects);
    }

    public function dump($value, $inline = 0, $dumpObjects = false)
    {
        if (\is_resource($value)) {
            return '%Resource%';
        }

        if (\is_array($value) || \is_object($value)) {
            return '%'.\gettype($value).'% '.$this->encode($value, $inline, $dumpObjects);
        }

        return $this->encode($value, $inline, $dumpObjects);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'yaml';
    }
}
