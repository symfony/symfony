<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow\StepAccessor;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class PropertyPathStepAccessor implements StepAccessorInterface
{
    public function __construct(
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly PropertyPathInterface $propertyPath,
    ) {
    }

    public function getStep(object|array $data, ?string $default = null): ?string
    {
        return $this->propertyAccessor->getValue($data, $this->propertyPath) ?: $default;
    }

    public function setStep(object|array &$data, string $step): void
    {
        $this->propertyAccessor->setValue($data, $this->propertyPath, $step);
    }
}
