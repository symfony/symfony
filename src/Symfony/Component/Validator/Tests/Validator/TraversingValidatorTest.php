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
use Symfony\Component\Validator\NodeTraverser\NodeVisitor\NodeValidator;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validator\Validator;

class TraversingValidatorTest extends AbstractValidatorTest
{
    protected function createValidator(MetadataFactoryInterface $metadataFactory)
    {
        $validatorFactory = new ConstraintValidatorFactory();
        $nodeTraverser = new NodeTraverser($metadataFactory);
        $nodeValidator = new NodeValidator($validatorFactory, $nodeTraverser);
        $contextManager = new ExecutionContextManager($metadataFactory, $nodeValidator, new DefaultTranslator());
        $validator = new Validator($nodeTraverser, $metadataFactory, $contextManager);

        $contextManager->initialize($validator);
        $nodeValidator->setContextManager($contextManager);

        $nodeTraverser->addVisitor($contextManager);
        $nodeTraverser->addVisitor($nodeValidator);

        return $validator;
    }
}
