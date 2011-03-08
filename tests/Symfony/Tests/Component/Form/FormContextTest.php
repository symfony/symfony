<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/Fixtures/Author.php';
require_once __DIR__ . '/Fixtures/TestField.php';

use Symfony\Component\Form\FormContext;
use Symfony\Component\Form\CsrfProvider\DefaultCsrfProvider;

class FormContextTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
    }

    public function testBuildDefaultWithCsrfProtection()
    {
        $context = FormContext::buildDefault($this->validator, 'secret');

        $expected = array(
            'validator' => $this->validator,
            'csrf_provider' => new DefaultCsrfProvider('secret'),
            'context' => $context,
        );

        $this->assertEquals($expected, $context->getOptions());
    }

    public function testBuildDefaultWithoutCsrfProtection()
    {
        $context = FormContext::buildDefault($this->validator, null, false);

        $expected = array(
            'validator' => $this->validator,
            'context' => $context,
        );

        $this->assertEquals($expected, $context->getOptions());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testBuildDefaultWithoutCsrfSecretThrowsException()
    {
        FormContext::buildDefault($this->validator, null, true);
    }
}
