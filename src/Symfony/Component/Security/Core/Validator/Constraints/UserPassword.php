<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class UserPassword extends Constraint
{
    public const INVALID_PASSWORD_ERROR = '2d2a8bb4-ddc8-45e4-9b0f-8670d3a3e290';

    protected const ERROR_NAMES = [
        self::INVALID_PASSWORD_ERROR => 'INVALID_PASSWORD_ERROR',
    ];

    public $message = 'This value should be the user\'s current password.';
    public $service = 'security.validator.user_password';

    public function __construct(array $options = null, string $message = null, string $service = null, array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->service = $service ?? $this->service;
    }

    public function validatedBy(): string
    {
        return $this->service;
    }
}
