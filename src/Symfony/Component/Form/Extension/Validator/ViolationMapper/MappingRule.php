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
class MappingRule extends AbstractMappingRule
{
    private $propertyPath;

    public function __construct(FormInterface $origin, string $propertyPath, string $targetPath)
    {
        parent::__construct($origin, $targetPath);

        $this->propertyPath = $propertyPath;
    }

    /**
     * @return FormInterface
     */
    public function getOrigin()
    {
        return $this->origin;
    }

    /**
     * {@inheritdoc}
     */
    public function match($propertyPath)
    {
        if ($propertyPath === $this->propertyPath) {
            return $this->getTarget();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isPrefix($propertyPath)
    {
        $length = \strlen($propertyPath);
        $prefix = substr($this->propertyPath, 0, $length);
        $next = isset($this->propertyPath[$length]) ? $this->propertyPath[$length] : null;

        return $prefix === $propertyPath && ('[' === $next || '.' === $next);
    }

    /**
     * @return FormInterface
     *
     * @throws ErrorMappingException
     */
    public function getTarget()
    {
        return $this->doGetTarget($this->targetPath);
    }
}
