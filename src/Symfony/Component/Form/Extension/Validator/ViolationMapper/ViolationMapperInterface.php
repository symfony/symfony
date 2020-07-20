<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\ViolationMapper;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ViolationMapperInterface
{
    /**
     * Maps a constraint violation to a form in the form tree under
     * the given form.
     *
     * @param bool $allowNonSynchronized Whether to allow mapping to non-synchronized forms
     */
    public function mapViolation(ConstraintViolation $violation, FormInterface $form, bool $allowNonSynchronized = false);
}
