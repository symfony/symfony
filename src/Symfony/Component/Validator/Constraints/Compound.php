<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Extend this class to create a reusable set of constraints.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
abstract class Compound extends Composite
{
    /** @var Constraint[] */
    public array $constraints = [];

    public function __construct(mixed $options = null)
    {
        if (isset($options[$this->getCompositeOption()])) {
            throw new ConstraintDefinitionException(sprintf('You can\'t redefine the "%s" option. Use the "%s::getConstraints()" method instead.', $this->getCompositeOption(), __CLASS__));
        }

        $this->constraints = $this->getConstraints($this->normalizeOptions($options));

        parent::__construct($options);
    }

    final protected function getCompositeOption(): string
    {
        return 'constraints';
    }

    final public function validatedBy(): string
    {
        return CompoundValidator::class;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return Constraint[]
     */
    abstract protected function getConstraints(array $options): array;
}
