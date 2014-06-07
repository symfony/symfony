<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Context;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Context\LegacyExecutionContext;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Validator\RecursiveValidator;

class LegacyExecutionContextTest extends \PHPUnit_Framework_TestCase
{
    const TRANS_DOMAIN = 'trans_domain';

    private $metadataFactory;
    private $translator;
    private $validator;

    protected function setUp()
    {
        $this->metadataFactory = $this->getMock('Symfony\Component\Validator\MetadataFactoryInterface');
        $this->translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');

        $validatorFactory = new ConstraintValidatorFactory();
        $executionContextFactory = new ExecutionContextFactory($this->translator, self::TRANS_DOMAIN);
        $this->validator = new RecursiveValidator($executionContextFactory, $this->metadataFactory, $validatorFactory);
    }

    public function testGetPropertyPathWithNestedCollectionsAndAllMixed()
    {
        $constraints = new Collection(array(
            'shelves' => new All(array('constraints' => array(
                new Collection(array(
                    'name'  => new ConstraintA(),
                    'books' => new All(array('constraints' => array(
                        new ConstraintA()
                    )))
                ))
            ))),
            'name' => new ConstraintA()
        ));
        $data = array(
            'shelves' => array(
                array(
                    'name' => 'Research',
                    'books' => array('foo', 'bar'),
                ),
                array(
                    'name' => 'VALID',
                    'books' => array('foozy', 'VALID', 'bazzy'),
                ),
            ),
            'name' => 'Library',
        );
        $expectedViolationPaths = array(
            '[shelves][0][name]',
            '[shelves][0][books][0]',
            '[shelves][0][books][1]',
            '[shelves][1][books][0]',
            '[shelves][1][books][2]',
            '[name]',
        );

        $context = new LegacyExecutionContext($this->validator, 'Root', $this->metadataFactory, $this->translator, self::TRANS_DOMAIN);
        $context->validateValue($data, $constraints);

        foreach ($context->getViolations() as $violation) {
            $violationPaths[] = $violation->getPropertyPath();
        }

        $this->assertEquals($expectedViolationPaths, $violationPaths);
    }
}
