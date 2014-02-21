<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Validator;

use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\NodeVisitor\ContextUpdateVisitor;
use Symfony\Component\Validator\NodeVisitor\DefaultGroupReplacingVisitor;
use Symfony\Component\Validator\NodeVisitor\NodeValidationVisitor;
use Symfony\Component\Validator\NodeTraverser\NonRecursiveNodeTraverser;
use Symfony\Component\Validator\Validator\Validator;

class Validator2Dot5ApiTest extends Abstract2Dot5ApiTest
{
    protected function createValidator(MetadataFactoryInterface $metadataFactory)
    {
        $nodeTraverser = new NonRecursiveNodeTraverser($metadataFactory);
        $nodeValidator = new NodeValidationVisitor($nodeTraverser, new ConstraintValidatorFactory());
        $contextFactory = new ExecutionContextFactory($nodeValidator, new DefaultTranslator());
        $validator = new Validator($contextFactory, $nodeTraverser, $metadataFactory);
        $groupSequenceResolver = new DefaultGroupReplacingVisitor();
        $contextRefresher = new ContextUpdateVisitor();

        $nodeTraverser->addVisitor($groupSequenceResolver);
        $nodeTraverser->addVisitor($contextRefresher);
        $nodeTraverser->addVisitor($nodeValidator);

        return $validator;
    }
}
