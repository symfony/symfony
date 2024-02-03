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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Yaml extends Constraint
{
    public const INVALID_YAML_ERROR = '63313a31-837c-42bb-99eb-542c76aacc48';

    protected const ERROR_NAMES = [
        self::INVALID_YAML_ERROR => 'INVALID_YAML_ERROR',
    ];

    public $message = 'This value should be valid YAML.';
    public $flags = 0;

    public function __construct(
        ?array $options = null,
        ?string $message = null,
        ?int $flags = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->flags = $flags ?? $this->flags;
    }
}