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
use Symfony\Component\Validator\Context\LegacyExecutionContextFactory;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\NodeVisitor\ContextRefresher;
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
        $contextFactory = new LegacyExecutionContextFactory($nodeValidator, new DefaultTranslator());
        $validator = new LegacyValidator($contextFactory, $nodeTraverser, $metadataFactory);
        $groupSequenceResolver = new GroupSequenceResolver();
        $contextRefresher = new ContextRefresher();

        $nodeTraverser->addVisitor($groupSequenceResolver);
        $nodeTraverser->addVisitor($contextRefresher);
        $nodeTraverser->addVisitor($nodeValidator);

        return $validator;
    }
}
