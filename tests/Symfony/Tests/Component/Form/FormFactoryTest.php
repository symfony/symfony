<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/Fixtures/Author.php';
require_once __DIR__ . '/Fixtures/TestField.php';

use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormContext;
use Symfony\Component\Form\CsrfProvider\DefaultCsrfProvider;

class FormFactoryTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
    }

    public function testBuildDefaultWithCsrfProtection()
    {
        $factory = FormFactory::buildDefault($this->validator, 'secret');

        $context = new FormContext();
        $context->validator($this->validator);
        $context->csrfProtection(true);
        $context->csrfProvider(new DefaultCsrfProvider('secret'));

        $this->assertEquals(new FormFactory($context), $factory);
    }

    public function testBuildDefaultWithoutCsrfProtection()
    {
        $factory = FormFactory::buildDefault($this->validator, null, false);

        $context = new FormContext();
        $context->validator($this->validator);
        $context->csrfProtection(false);

        $this->assertEquals(new FormFactory($context), $factory);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testBuildDefaultWithoutCsrfSecretThrowsException()
    {
        FormFactory::buildDefault($this->validator, null, true);
    }
}
