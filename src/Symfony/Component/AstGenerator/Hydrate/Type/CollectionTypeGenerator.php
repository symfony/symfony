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
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\Exception\MissingContextException;
use Symfony\Component\PropertyInfo\Type;

/**
 * Abstract class to generate collection hydration
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class CollectionTypeGenerator implements AstGeneratorInterface
{
    const COLLECTION_WITH_STDCLASS = 0;
    const COLLECTION_WITH_ARRAY = 1;

    /**
     * CollectionTypeGenerator constructor.
     *
     * @param AstGeneratorInterface $subValueTypeGenerator Generator for the value of the collection
     * @param int|null              $fromCollectionType    From collection type to generate, array or stdClass, use null
     * to have a dynamic choice depending on the type of the collection key (where int will be array and string stdClass)
     * @param int|null              $toCollectionType      To collection type to generate, array or stdClass, use null
     * to have a dynamic choice depending on the type of the collection key (where int will be array and string stdClass)
     */
    public function __construct(AstGeneratorInterface $subValueTypeGenerator, $fromCollectionType = null, $toCollectionType = null)
    {

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

        $statements = [
            new Expr\Assign($context['output'], $this->createCollectionAssignStatement()),
        ];

        $loopValueVar = new Expr\Variable($context->getUniqueVariableName('value'));
        $loopKeyVar = $this->createLoopKeyStatement($context);
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
    protected function createCollectionAssignStatement()
    {

    }
}
