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
 * @internal
 */
abstract class AbstractMappingRule
{
    protected $origin;

    protected $targetPath;

    public function __construct(FormInterface $origin, string $targetPath)
    {
        $this->origin = $origin;
        $this->targetPath = $targetPath;
    }

    protected function doGetTarget(string $targetPath): FormInterface
    {
        $childNames = explode('.', $targetPath);
        $target = $this->origin;

        foreach ($childNames as $childName) {
            if (!$target->has($childName)) {
                throw new ErrorMappingException(sprintf('The child "%s" of "%s" mapped by the rule "%s" in "%s" does not exist.', $childName, $target->getName(), $this->targetPath, $this->origin->getName()));
            }

            $target = $target->get($childName);
        }

        return $target;
    }

    /**
     * Matches a property path against the rule path.
     *
     * If the rule matches, the form mapped by the rule is returned.
     * Otherwise this method returns null.
     *
     * @param string $propertyPath The property path to match against the rule
     *
     * @return FormInterface|null The mapped form or null
     */
    abstract public function match($propertyPath);

    /**
     * Matches a property path against a prefix of the rule path.
     *
     * @param string $propertyPath The property path to match against the rule
     *
     * @return bool Whether the property path is a prefix of the rule or not
     */
    abstract public function isPrefix($propertyPath);
}
