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
    private $origin;
    private $propertyPath;
    private $targetPath;

    public function __construct(FormInterface $origin, string $propertyPath, string $targetPath)
    {
        $this->origin = $origin;
        $this->propertyPath = $propertyPath;
        $this->targetPath = $targetPath;
    }

    /**
     * @return FormInterface
     */
    public function getOrigin()
    {
        return $this->origin;
    }

    /**
     * Matches a property path against the rule path.
     *
     * If the rule matches, the form mapped by the rule is returned.
     * Otherwise this method returns false.
     *
     * @return FormInterface|null
     */
    public function match(string $propertyPath)
    {
        return $propertyPath === $this->propertyPath ? $this->getTarget() : null;
    }

    /**
     * Matches a property path against a prefix of the rule path.
     *
     * @return bool
     */
    public function isPrefix(string $propertyPath)
    {
        $length = \strlen($propertyPath);
        $prefix = substr($this->propertyPath, 0, $length);
        $next = $this->propertyPath[$length] ?? null;

        return $prefix === $propertyPath && ('[' === $next || '.' === $next);
    }

    /**
     * @return FormInterface
     *
     * @throws ErrorMappingException
     */
    public function getTarget()
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
