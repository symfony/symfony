<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * The configuration of a {@link Form} object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormConfigInterface
{
    /**
     * Returns the event dispatcher used to dispatch form events.
     */
    public function getEventDispatcher(): EventDispatcherInterface;

    /**
     * Returns the name of the form used as HTTP parameter.
     */
    public function getName(): string;

    /**
     * Returns the property path that the form should be mapped to.
     */
    public function getPropertyPath(): ?PropertyPathInterface;

    /**
     * Returns whether the form should be mapped to an element of its
     * parent's data.
     */
    public function getMapped(): bool;

    /**
     * Returns whether the form's data should be modified by reference.
     */
    public function getByReference(): bool;

    /**
     * Returns whether the form should read and write the data of its parent.
     */
    public function getInheritData(): bool;

    /**
     * Returns whether the form is compound.
     *
     * This property is independent of whether the form actually has
     * children. A form can be compound and have no children at all, like
     * for example an empty collection form.
     * The contrary is not possible, a form which is not compound
     * cannot have any children.
     */
    public function getCompound(): bool;

    /**
     * Returns the resolved form type used to construct the form.
     */
    public function getType(): ResolvedFormTypeInterface;

    /**
     * Returns the view transformers of the form.
     *
     * @return DataTransformerInterface[]
     */
    public function getViewTransformers(): array;

    /**
     * Returns the model transformers of the form.
     *
     * @return DataTransformerInterface[]
     */
    public function getModelTransformers(): array;

    /**
     * Returns the data mapper of the compound form or null for a simple form.
     */
    public function getDataMapper(): ?DataMapperInterface;

    /**
     * Returns whether the form is required.
     */
    public function getRequired(): bool;

    /**
     * Returns whether the form is disabled.
     */
    public function getDisabled(): bool;

    /**
     * Returns whether errors attached to the form will bubble to its parent.
     */
    public function getErrorBubbling(): bool;

    /**
     * Used when the view data is empty on submission.
     *
     * When the form is compound it will also be used to map the
     * children data.
     *
     * The empty data must match the view format as it will passed to the first view transformer's
     * "reverseTransform" method.
     */
    public function getEmptyData(): mixed;

    /**
     * Returns additional attributes of the form.
     */
    public function getAttributes(): array;

    /**
     * Returns whether the attribute with the given name exists.
     */
    public function hasAttribute(string $name): bool;

    /**
     * Returns the value of the given attribute.
     */
    public function getAttribute(string $name, mixed $default = null): mixed;

    /**
     * Returns the initial data of the form.
     */
    public function getData(): mixed;

    /**
     * Returns the class of the view data or null if the data is scalar or an array.
     */
    public function getDataClass(): ?string;

    /**
     * Returns whether the form's data is locked.
     *
     * A form with locked data is restricted to the data passed in
     * this configuration. The data can only be modified then by
     * submitting the form.
     */
    public function getDataLocked(): bool;

    /**
     * Returns the form factory used for creating new forms.
     */
    public function getFormFactory(): FormFactoryInterface;

    /**
     * Returns the target URL of the form.
     */
    public function getAction(): string;

    /**
     * Returns the HTTP method used by the form.
     */
    public function getMethod(): string;

    /**
     * Returns the request handler used by the form.
     */
    public function getRequestHandler(): RequestHandlerInterface;

    /**
     * Returns whether the form should be initialized upon creation.
     */
    public function getAutoInitialize(): bool;

    /**
     * Returns all options passed during the construction of the form.
     *
     * @return array<string, mixed> The passed options
     */
    public function getOptions(): array;

    /**
     * Returns whether a specific option exists.
     */
    public function hasOption(string $name): bool;

    /**
     * Returns the value of a specific option.
     */
    public function getOption(string $name, mixed $default = null): mixed;

    /**
     * Returns a callable that takes the model data as argument and that returns if it is empty or not.
     */
    public function getIsEmptyCallback(): ?callable;
}
