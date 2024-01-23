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

use Symfony\Component\Intl\Locales;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Locale extends Constraint
{
    public const NO_SUCH_LOCALE_ERROR = 'a0af4293-1f1a-4a1c-a328-979cba6182a2';

    protected const ERROR_NAMES = [
        self::NO_SUCH_LOCALE_ERROR => 'NO_SUCH_LOCALE_ERROR',
    ];

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public $message = 'This value is not a valid locale.';
    public $canonicalize = true;

    public function __construct(
        ?array $options = null,
        ?string $message = null,
        ?bool $canonicalize = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        if (!class_exists(Locales::class)) {
            throw new LogicException('The Intl component is required to use the Locale constraint. Try running "composer require symfony/intl".');
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->canonicalize = $canonicalize ?? $this->canonicalize;
    }
}
