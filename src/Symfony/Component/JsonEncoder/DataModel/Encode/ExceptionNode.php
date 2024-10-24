<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\DataModel\Encode;

use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use Symfony\Component\JsonEncoder\DataModel\DataAccessorInterface;
use Symfony\Component\JsonEncoder\DataModel\PhpExprDataAccessor;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * Represent an exception to be thrown.
 *
 * Exceptions are leaves in the data model tree.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class ExceptionNode implements DataModelNodeInterface
{
    /**
     * @param class-string<\Exception> $className
     */
    public function __construct(
        private string $className,
    ) {
    }

    public function getAccessor(): DataAccessorInterface
    {
        return new PhpExprDataAccessor(new New_(new FullyQualified($this->className)));
    }

    public function getType(): ObjectType
    {
        return Type::object($this->className);
    }
}
