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

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Yaml\Parser;

/**
 * @author Kev <https://github.com/symfonyaml>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Yaml extends Constraint
{
    public const INVALID_YAML_ERROR = '63313a31-837c-42bb-99eb-542c76aacc48';

    protected const ERROR_NAMES = [
        self::INVALID_YAML_ERROR => 'INVALID_YAML_ERROR',
    ];

    /**
     * @param int-mask-of<\Symfony\Component\Yaml\Yaml::PARSE_*> $flags
     * @param string[]|null                                      $groups
     */
    #[HasNamedArguments]
    public function __construct(
        public string $message = 'This value is not valid YAML.',
        public int $flags = 0,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (!class_exists(Parser::class)) {
            throw new LogicException('The Yaml component is required to use the Yaml constraint. Try running "composer require symfony/yaml".');
        }

        parent::__construct(null, $groups, $payload);
    }
}
