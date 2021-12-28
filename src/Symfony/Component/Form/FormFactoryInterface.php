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

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * Allows creating a form based on a name, a class or a property.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormFactoryInterface
{
    /**
     * Returns a form.
     *
     * @see createBuilder()
     *
     * @param mixed $data The initial data
     *
     * @return FormInterface
     *
     * @throws InvalidOptionsException if any given option is not applicable to the given type
     */
    public function create(string $type = FormType::class, $data = null, array $options = []);

    /**
     * Returns a form.
     *
     * @see createNamedBuilder()
     *
     * @param mixed $data The initial data
     *
     * @return FormInterface
     *
     * @throws InvalidOptionsException if any given option is not applicable to the given type
     */
    public function createNamed(string $name, string $type = FormType::class, $data = null, array $options = []);

    /**
     * Returns a form for a property of a class.
     *
     * @see createBuilderForProperty()
     *
     * @param string $class    The fully qualified class name
     * @param string $property The name of the property to guess for
     * @param mixed  $data     The initial data
     *
     * @return FormInterface
     *
     * @throws InvalidOptionsException if any given option is not applicable to the form type
     */
    public function createForProperty(string $class, string $property, $data = null, array $options = []);

    /**
     * Returns a form builder.
     *
     * @param mixed $data The initial data
     *
     * @return FormBuilderInterface
     *
     * @throws InvalidOptionsException if any given option is not applicable to the given type
     */
    public function createBuilder(string $type = FormType::class, $data = null, array $options = []);

    /**
     * Returns a form builder.
     *
     * @param mixed $data The initial data
     *
     * @return FormBuilderInterface
     *
     * @throws InvalidOptionsException if any given option is not applicable to the given type
     */
    public function createNamedBuilder(string $name, string $type = FormType::class, $data = null, array $options = []);

    /**
     * Returns a form builder for a property of a class.
     *
     * If any of the 'required' and type options can be guessed,
     * and are not provided in the options argument, the guessed value is used.
     *
     * @param string $class    The fully qualified class name
     * @param string $property The name of the property to guess for
     * @param mixed  $data     The initial data
     *
     * @return FormBuilderInterface
     *
     * @throws InvalidOptionsException if any given option is not applicable to the form type
     */
    public function createBuilderForProperty(string $class, string $property, $data = null, array $options = []);
}
