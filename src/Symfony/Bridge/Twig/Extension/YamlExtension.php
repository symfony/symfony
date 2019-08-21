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
 *
 * @final since Symfony 4.4
 */
class YamlExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     *
     * @return TwigFilter[]
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
            return $dumper->dump($input, $inline, 0, $dumpObjects);
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
