<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow;

use Symfony\Component\Form\Flow\DataStorage\DataStorageInterface;
use Symfony\Component\Form\Flow\StepAccessor\StepAccessorInterface;
use Symfony\Component\Form\FormConfigInterface;

/**
 * The configuration of a {@link FormFlow} object.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface FormFlowConfigInterface extends FormConfigInterface
{
    /**
     * Checks if a step with the given name exists.
     */
    public function hasStep(string $name): bool;

    /**
     * Returns the step with the given name.
     */
    public function getStep(string $name): StepFlowConfigInterface;

    /**
     * Returns all steps.
     *
     * @return array<string, StepFlowConfigInterface>
     */
    public function getSteps(): array;

    /**
     * Returns the name of the initial step.
     */
    public function getInitialStep(): string;

    /**
     * Returns the initial options for the form flow.
     *
     * @return array<string, mixed>
     */
    public function getInitialOptions(): array;

    /**
     * Returns the data storage for the form flow.
     */
    public function getDataStorage(): DataStorageInterface;

    /**
     * Returns the step accessor for the form flow.
     */
    public function getStepAccessor(): StepAccessorInterface;

    /**
     * Checks if the form flow is configured to auto reset once it's finished.
     */
    public function isAutoReset(): bool;
}
