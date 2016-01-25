<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator\Hydrate\Type;

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\Exception\MissingContextException;
use Symfony\Component\AstGenerator\UniqueVariableScope;
use Symfony\Component\PropertyInfo\Type;

/**
 * Abstract class to generate collection hydration
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class CollectionTypeGenerator implements AstGeneratorInterface
{
    const COLLECTION_WITH_OBJECT = 0;
    const COLLECTION_WITH_ARRAY = 1;

    const OBJECT_ASSIGNMENT_ARRAY = 0;
    const OBJECT_ASSIGNMENT_PROPERTY = 1;

    /** @var AstGeneratorInterface Generator for the value of the collection */
    private $subValueTypeGenerator;

    /** @var int|null Output collection type to generate */
    private $outputCollectionType;

    /** @var string Class of object to use for the collection of the output */
    private $outputObjectClass;

    /** @var int Assignment type for the output */
    private $outputObjectAssignment;

    /**
     * CollectionTypeGenerator constructor.
     *
     * @param AstGeneratorInterface $subValueTypeGenerator Generator for the value of the collection
     * @param int|null              $outputCollectionType   Output collection type to generate, array or stdClass, use null
     * to have a dynamic choice depending on the type of the collection key (where int will be array and string stdClass)
     * @param string                $outputObjectClass      Class of object to use for the collection of the output
     * @param int                   $outputObjectAssignment Assignment type for the output
     */
    public function __construct(
        AstGeneratorInterface $subValueTypeGenerator,
        $outputCollectionType = null,
        $outputObjectClass = '\\stdClass',
        $outputObjectAssignment = self::OBJECT_ASSIGNMENT_PROPERTY
    )
    {
        $this->subValueTypeGenerator = $subValueTypeGenerator;
        $this->outputCollectionType = $outputCollectionType;
        $this->outputObjectClass = $outputObjectClass;
        $this->outputObjectAssignment = $outputObjectAssignment;
    }

    /**
     * {@inheritdoc}
     *
     * @param Type $object A type extracted with PropertyInfo component
     */
    public function generate($object, array $context = [])
    {
        if (!isset($context['input']) || !($context['input'] instanceof Expr)) {
            throw new MissingContextException('Input variable not defined or not an Expr in generation context');
        }

        if (!isset($context['output']) || !($context['output'] instanceof Expr)) {
            throw new MissingContextException('Output variable not defined or not an Expr in generation context');
        }

        $uniqueVariableScope = isset($context['unique_variable_scope']) ? $context['unique_variable_scope'] : new UniqueVariableScope();
        $statements = [
            new Expr\Assign($context['output'], $this->createCollectionAssignStatement($object)),
        ];

        // Create item input
        $loopValueVar = new Expr\Variable($uniqueVariableScope->getUniqueName('value'));

        // Create item output
        $loopKeyVar = new Expr\Variable($uniqueVariableScope->getUniqueName('key'));
        $output = $this->createCollectionItemExpr($object, $loopKeyVar, $context['output']);

        // Loop statements
        $loopStatements = [new Expr\Assign($output, $loopValueVar)];

        if (null !== $object->getCollectionValueType() && $this->subValueTypeGenerator->supportsGeneration($object->getCollectionValueType())) {
            $loopStatements = $this->subValueTypeGenerator->generate($object->getCollectionValueType(), array_merge($context, [
                'input' => $loopValueVar,
                'output' => $output
            ]));
        }

        $statements[] = new Stmt\Foreach_($context['input'], $loopValueVar, [
            'keyVar' => $loopKeyVar,
            'stmts'  => $loopStatements
        ]);

        return $statements;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsGeneration($object)
    {
        return $object instanceof Type && $object->isCollection();
    }

    /**
     * Create the collection assign statement
     *
     * @return Expr
     */
    protected function createCollectionAssignStatement(Type $type)
    {
        $outputCollectionType = $this->getOutputCollectionType($type);

        if ($outputCollectionType === self::COLLECTION_WITH_ARRAY) {
            return new Expr\Array_();
        }

        return new Expr\New_(new Name($this->outputObjectClass));
    }

    /**
     * Create the expression for the output assignment of an item in the array
     *
     * @param Type          $type       Type of property
     * @param Expr\Variable $loopKeyVar Variable for the key in the loop
     * @param Expr          $output     Output to use for the collection
     *
     * @return Expr\ArrayDimFetch|Expr\PropertyFetch
     */
    protected function createCollectionItemExpr(Type $type, Expr\Variable $loopKeyVar, Expr $output)
    {
        $outputCollectionType = $this->getOutputCollectionType($type);

        if ($outputCollectionType === self::COLLECTION_WITH_ARRAY || $this->outputObjectAssignment == self::OBJECT_ASSIGNMENT_ARRAY) {
            return new Expr\ArrayDimFetch($output, $loopKeyVar);
        }

        return new Expr\PropertyFetch($output, $loopKeyVar);
    }

    /**
     * Get output collection type, set in constructor or guessed from type of the collection key
     *
     * @param Type $type
     *
     * @return int|null
     */
    private function getOutputCollectionType(Type $type)
    {
        $outputCollectionType = $this->outputCollectionType;

        if ($outputCollectionType === null) {
            $outputCollectionType = self::COLLECTION_WITH_ARRAY;

            if ($type->getCollectionKeyType() !== null && $type->getCollectionKeyType()->getBuiltinType() !== Type::BUILTIN_TYPE_INT) {
                $outputCollectionType = self::COLLECTION_WITH_OBJECT;
            }
        }

        return $outputCollectionType;
    }
}
