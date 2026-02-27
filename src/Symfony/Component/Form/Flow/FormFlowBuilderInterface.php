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

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Flow\DataStorage\DataStorageInterface;
use Symfony\Component\Form\Flow\StepAccessor\StepAccessorInterface;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @extends \Traversable<string, FormBuilderInterface>
 */
interface FormFlowBuilderInterface extends FormBuilderInterface, FormFlowConfigInterface
{
    /**
     * Creates a new step builder.
     */
    public function createStep(string $name, string $type = FormType::class, array $options = []): StepFlowBuilderConfigInterface;

    /**
     * Adds a step to the form flow.
     */
    public function addStep(StepFlowBuilderConfigInterface|string $name, string $type = FormType::class, array $options = [], ?callable $skip = null, int $priority = 0): static;

    /**
     * Removes a step from the form flow.
     */
    public function removeStep(string $name): static;

    /**
     * Returns a step builder by name.
     */
    public function getStep(string $name): StepFlowBuilderConfigInterface;

    /**
     * Returns all step builders.
     *
     * @return array<string, StepFlowBuilderConfigInterface>
     */
    public function getSteps(): array;

    /**
     * Sets the initial options for the form flow.
     *
     * @param array<string, mixed> $options
     */
    public function setInitialOptions(array $options): static;

    /**
     * Sets the data storage for the form flow.
     */
    public function setDataStorage(DataStorageInterface $dataStorage): static;

    /**
     * Sets the step accessor for the form flow.
     */
    public function setStepAccessor(StepAccessorInterface $stepAccessor): static;

    /**
     * Creates and returns the form flow instance.
     */
    public function getForm(): FormFlowInterface;
}
