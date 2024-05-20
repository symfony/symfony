<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

/**
 * Get the FQCN of a parameter default constant value.
 */
final class ConstCaster
{
    public static function castParameterDefaultValue(\ReflectionParameter $param): ?string
    {
        echo (new \Exception())->getTraceAsString() . "\n\n";
        $namespacedConstant = $param->getDefaultValueConstantName();
        var_dump($namespacedConstant);

        if (null !== $namespacedConstant && str_contains($namespacedConstant, '\\') && !\defined($namespacedConstant)) {
            $globalConstant = '\\'.preg_replace('/^.*\\\\([^\\\\]+)$/', '$1', $namespacedConstant);

            if (\defined($globalConstant) && $param->getDefaultValue() === \constant($globalConstant)) {
                var_dump($globalConstant);
                exit;
                return $globalConstant;
            }
        }

        exit;
        return $namespacedConstant;
    }
}
