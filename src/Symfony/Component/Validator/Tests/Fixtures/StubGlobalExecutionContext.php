<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\GlobalExecutionContextInterface;
use Symfony\Component\Validator\ValidationVisitorInterface;

/**
 * @since 2.5.3
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated
 */
class StubGlobalExecutionContext implements GlobalExecutionContextInterface
{
    private $violations;

    private $root;

    private $visitor;

    public function __construct($root = null, ValidationVisitorInterface $visitor = null)
    {
        $this->violations = new ConstraintViolationList();
        $this->root = $root;
        $this->visitor = $visitor;
    }

    public function getViolations()
    {
        return $this->violations;
    }

    public function setRoot($root)
    {
        $this->root = $root;
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function setVisitor(ValidationVisitorInterface $visitor)
    {
        $this->visitor = $visitor;
    }

    public function getVisitor()
    {
        return $this->visitor;
    }

    public function getValidatorFactory()
    {
    }

    public function getMetadataFactory()
    {
    }
}
