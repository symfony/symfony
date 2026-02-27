<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\FormFlow\Data\Register;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\FormFlow\RegisterType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Test\FormIntegrationTestCase;

class FormExtensionFlowHelpersTest extends FormIntegrationTestCase
{
    private FormExtension $rawExtension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rawExtension = new FormExtension();
    }

    public function testFlowAtFirstStep()
    {
        $data = new Register();

        $view = $this->factory->create(RegisterType::class, $data)
            ->getStepForm()
            ->createView();

        $this->assertSame(3, $this->rawExtension->getFormFlowTotalSteps($view));
        $this->assertSame(['organization', 'credentials', 'confirmation'], $this->rawExtension->getFormFlowSteps($view));
        $this->assertSame('organization', $this->rawExtension->getFormFlowCurrentStep($view));
        $this->assertSame(0, $this->rawExtension->getFormFlowStepIndex($view));
        $this->assertSame('credentials', $this->rawExtension->getFormFlowNextStep($view));
        $this->assertNull($this->rawExtension->getFormFlowPreviousStep($view));
        $this->assertSame('organization', $this->rawExtension->getFormFlowFirstStep($view));
        $this->assertSame('confirmation', $this->rawExtension->getFormFlowLastStep($view));
        $this->assertTrue($this->rawExtension->isFormFlowFirstStep($view));
        $this->assertFalse($this->rawExtension->isFormFlowLastStep($view));
        $this->assertTrue($this->rawExtension->canFormFlowMoveNext($view));
        $this->assertFalse($this->rawExtension->canFormFlowMoveBack($view));
    }

    public function testFlowAtMiddleStep()
    {
        $data = new Register();
        $data->currentStep = 'credentials';

        $view = $this->factory->create(RegisterType::class, $data)
            ->getStepForm()
            ->createView();

        $this->assertSame(3, $this->rawExtension->getFormFlowTotalSteps($view));
        $this->assertSame(['organization', 'credentials', 'confirmation'], $this->rawExtension->getFormFlowSteps($view));
        $this->assertSame('credentials', $this->rawExtension->getFormFlowCurrentStep($view));
        $this->assertSame(1, $this->rawExtension->getFormFlowStepIndex($view));
        $this->assertSame('confirmation', $this->rawExtension->getFormFlowNextStep($view));
        $this->assertSame('organization', $this->rawExtension->getFormFlowPreviousStep($view));
        $this->assertSame('organization', $this->rawExtension->getFormFlowFirstStep($view));
        $this->assertSame('confirmation', $this->rawExtension->getFormFlowLastStep($view));
        $this->assertFalse($this->rawExtension->isFormFlowFirstStep($view));
        $this->assertFalse($this->rawExtension->isFormFlowLastStep($view));
        $this->assertTrue($this->rawExtension->canFormFlowMoveNext($view));
        $this->assertTrue($this->rawExtension->canFormFlowMoveBack($view));
    }

    public function testFlowAtLastStep()
    {
        $data = new Register();
        $data->currentStep = 'confirmation';

        $view = $this->factory->create(RegisterType::class, $data)
            ->getStepForm()
            ->createView();

        $this->assertSame(3, $this->rawExtension->getFormFlowTotalSteps($view));
        $this->assertSame(['organization', 'credentials', 'confirmation'], $this->rawExtension->getFormFlowSteps($view));
        $this->assertSame('confirmation', $this->rawExtension->getFormFlowCurrentStep($view));
        $this->assertSame(2, $this->rawExtension->getFormFlowStepIndex($view));
        $this->assertNull($this->rawExtension->getFormFlowNextStep($view));
        $this->assertSame('credentials', $this->rawExtension->getFormFlowPreviousStep($view));
        $this->assertSame('organization', $this->rawExtension->getFormFlowFirstStep($view));
        $this->assertSame('confirmation', $this->rawExtension->getFormFlowLastStep($view));
        $this->assertFalse($this->rawExtension->isFormFlowFirstStep($view));
        $this->assertTrue($this->rawExtension->isFormFlowLastStep($view));
        $this->assertFalse($this->rawExtension->canFormFlowMoveNext($view));
        $this->assertTrue($this->rawExtension->canFormFlowMoveBack($view));
    }

    public function testFormWithoutFlow()
    {
        $data = new Register();

        $view = $this->factory->create(FormType::class, $data)
            ->createView();

        $this->assertNull($this->rawExtension->getFormFlowTotalSteps($view));
        $this->assertNull($this->rawExtension->getFormFlowSteps($view));
        $this->assertNull($this->rawExtension->getFormFlowCurrentStep($view));
        $this->assertNull($this->rawExtension->getFormFlowStepIndex($view));
        $this->assertNull($this->rawExtension->getFormFlowNextStep($view));
        $this->assertNull($this->rawExtension->getFormFlowPreviousStep($view));
        $this->assertNull($this->rawExtension->getFormFlowFirstStep($view));
        $this->assertNull($this->rawExtension->getFormFlowLastStep($view));
        $this->assertFalse($this->rawExtension->isFormFlowFirstStep($view));
        $this->assertFalse($this->rawExtension->isFormFlowLastStep($view));
        $this->assertFalse($this->rawExtension->canFormFlowMoveNext($view));
        $this->assertFalse($this->rawExtension->canFormFlowMoveBack($view));
    }
}
