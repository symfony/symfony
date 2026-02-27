<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Definition;

/**
 * Replaces env var placeholders by their current values.
 */
class ResolveEnvPlaceholdersPass extends AbstractRecursivePass
{
    protected bool $skipScalars = false;

    /**
     * @param string|true|null $format A sprintf() format returning the replacement for each env var name or
     *                                 null to resolve back to the original "%env(VAR)%" format or
     *                                 true to resolve to the actual values of the referenced env vars
     */
    public function __construct(
        private string|bool|null $format = true,
    ) {
    }

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if (\is_string($value)) {
            return $this->container->resolveEnvPlaceholders($value, $this->format);
        }
        if ($value instanceof Definition) {
            $changes = $value->getChanges();
            if (isset($changes['class'])) {
                $value->setClass($this->container->resolveEnvPlaceholders($value->getClass(), $this->format));
            }
            if (isset($changes['file'])) {
                $value->setFile($this->container->resolveEnvPlaceholders($value->getFile(), $this->format));
            }
        }

        $value = parent::processValue($value, $isRoot);

        if ($value && \is_array($value) && !$isRoot) {
            $value = array_combine($this->container->resolveEnvPlaceholders(array_keys($value), $this->format), $value);
        }

        return $value;
    }
}
