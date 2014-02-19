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
use Symfony\Component\Validator\Context\LegacyExecutionContextManager;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\NodeVisitor\GroupSequenceResolver;
use Symfony\Component\Validator\NodeVisitor\NodeValidator;
use Symfony\Component\Validator\NodeTraverser\NodeTraverser;
use Symfony\Component\Validator\Validator\LegacyValidator;

class LegacyValidator2Dot5ApiTest extends Abstract2Dot5ApiTest
{
    protected function createValidator(MetadataFactoryInterface $metadataFactory)
    {
        $nodeTraverser = new NodeTraverser($metadataFactory);
        $nodeValidator = new NodeValidator($nodeTraverser, new ConstraintValidatorFactory());
        $contextManager = new LegacyExecutionContextManager($nodeValidator, new DefaultTranslator());
        $validator = new LegacyValidator($nodeTraverser, $metadataFactory, $contextManager);
        $groupSequenceResolver = new GroupSequenceResolver();

        // The context manager needs the validator for passing it to created
        // contexts
        $contextManager->initialize($validator);

        // The node validator needs the context manager for passing the current
        // context to the constraint validators
        $nodeValidator->initialize($contextManager);

        $nodeTraverser->addVisitor($groupSequenceResolver);
        $nodeTraverser->addVisitor($contextManager);
        $nodeTraverser->addVisitor($nodeValidator);

        return $validator;
    }
}
