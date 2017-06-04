<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Egulias\EmailValidator\Validation\EmailValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Symfony\Component\Validator\Constraint;

/**
 * @author Toni Uebernickel <tuebernickel@gmail.com>
 */
class EguliasEmail extends Constraint
{
    const INVALID_EMAIL = 'C3219C70-49CB-459E-89C3-D4057F1164FF';

    /**
     * The violation message to use.
     *
     * The '{{ value }}' placeholder is provided containing the invalid value.
     *
     * @var string
     */
    public $message = 'This value is not a valid email address.';

    /**
     * Whether to ignore warnings provided by the RFC validation.
     *
     * @var bool
     */
    public $suppressRFCWarnings = false;

    /**
     * Whether to apply DNS check validation.
     *
     * @var bool
     */
    public $checkDNS = true;

    /**
     * Whether to apply literal spoof checking.
     *
     * @var bool
     */
    public $checkSpoof = false;

    /**
     * Configure a concrete list of validations to apply on the evaluated value.
     *
     * Providing a non-empty list will ignore any validation options.
     *
     * @var EmailValidation[]
     */
    public $validations = [];

    /**
     * Validation mode to apply.
     *
     * @var int
     */
    public $validationMode = MultipleValidationWithAnd::STOP_ON_ERROR;
}
