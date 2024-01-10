<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\ViolationMapper;

use Symfony\Component\Form\Exception\ErrorMappingException;
use Symfony\Component\Form\FormInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MappingRule
{
    public function __construct(
        private FormInterface $origin,
        private string $propertyPath,
        private string $targetPath,
    ) {
    }

    public function getOrigin(): FormInterface
    {
        return $this->origin;
    }

    /**
     * Matches a property path against the rule path.
     *
     * If the rule matches, the form mapped by the rule is returned.
     * Otherwise this method returns false.
     */
    public function match(string $propertyPath): ?FormInterface
    {
        return $propertyPath === $this->propertyPath ? $this->getTarget() : null;
    }

    /**
     * Matches a property path against a prefix of the rule path.
     */
    public function isPrefix(string $propertyPath): bool
    {
        $length = \strlen($propertyPath);
        $prefix = substr($this->propertyPath, 0, $length);
        $next = $this->propertyPath[$length] ?? null;

        return $prefix === $propertyPath && ('[' === $next || '.' === $next);
    }

    /**
     * @throws ErrorMappingException
     */
    public function getTarget(): FormInterface
    {
        $childNames = explode('.', $this->targetPath);
        $target = $this->origin;

        foreach ($childNames as $childName) {
            if (!$target->has($childName)) {
                throw new ErrorMappingException(sprintf('The child "%s" of "%s" mapped by the rule "%s" in "%s" does not exist.', $childName, $target->getName(), $this->targetPath, $this->origin->getName()));
            }
            $target = $target->get($childName);
        }

        return $target;
    }
}
