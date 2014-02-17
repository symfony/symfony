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

use Symfony\Component\Validator\Context\ExecutionContextManager;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Tests\AbstractValidatorTest;
use Symfony\Component\Validator\NodeTraverser\NodeTraverser;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validator\NodeValidator;
use Symfony\Component\Validator\Validator\Validator;

class TraversingValidatorTest extends AbstractValidatorTest
{
    protected function createValidator(MetadataFactoryInterface $metadataFactory)
    {
        $nodeTraverser = new NodeTraverser($metadataFactory);
        $nodeValidator = new NodeValidator($nodeTraverser, new ConstraintValidatorFactory());
        $contextManager = new ExecutionContextManager($nodeValidator, new DefaultTranslator());
        $validator = new Validator($nodeTraverser, $metadataFactory, $contextManager);

        // The context manager needs the validator for passing it to created
        // contexts
        $contextManager->initialize($validator);

        // The node validator needs the context manager for passing the current
        // context to the constraint validators
        $nodeValidator->initialize($contextManager);

        $nodeTraverser->addVisitor($contextManager);
        $nodeTraverser->addVisitor($nodeValidator);

        return $validator;
    }
}
