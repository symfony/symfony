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
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides integration of the Yaml component with Twig.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class YamlExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('yaml_encode', $this->encode(...)),
            new TwigFilter('yaml_dump', $this->dump(...)),
        ];
    }

    public function encode(mixed $input, int $inline = 0, int $dumpObjects = 0): string
    {
        static $dumper;

        $dumper ??= new YamlDumper();

        if (\defined('Symfony\Component\Yaml\Yaml::DUMP_OBJECT')) {
            return $dumper->dump($input, $inline, 0, $dumpObjects);
        }

        return $dumper->dump($input, $inline, 0, false, $dumpObjects);
    }

    public function dump(mixed $value, int $inline = 0, int $dumpObjects = 0): string
    {
        if (\is_resource($value)) {
            return '%Resource%';
        }

        if (\is_array($value) || \is_object($value)) {
            return '%'.\gettype($value).'% '.$this->encode($value, $inline, $dumpObjects);
        }

        return $this->encode($value, $inline, $dumpObjects);
    }
}
