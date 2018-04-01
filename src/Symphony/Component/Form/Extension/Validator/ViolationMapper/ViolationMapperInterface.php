<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Validator\ViolationMapper;

use Symphony\Component\Form\FormInterface;
use Symphony\Component\Validator\ConstraintViolation;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ViolationMapperInterface
{
    /**
     * Maps a constraint violation to a form in the form tree under
     * the given form.
     *
     * @param ConstraintViolation $violation            The violation to map
     * @param FormInterface       $form                 The root form of the tree to map it to
     * @param bool                $allowNonSynchronized Whether to allow mapping to non-synchronized forms
     */
    public function mapViolation(ConstraintViolation $violation, FormInterface $form, $allowNonSynchronized = false);
}
