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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @Annotation
 *
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class NoBannedWords extends Constraint
{
    public const BANNED_WORDS_ERROR = 'd187ff45-bf23-4331-aa87-c24a36e9b400';

    protected const ERROR_NAMES = [
        self::BANNED_WORDS_ERROR => 'BANNED_WORDS_ERROR',
    ];

    public string $message = 'The value contains the following banned words: {{ wordList }}.';

    /**
     * @var array<string>
     */
    public array $dictionary = [];

    /**
     * @param array<string> $dictionary
     */
    public function __construct(array $dictionary = [], mixed $options = null, array $groups = null, mixed $payload = null)
    {
        array_walk($options['dictionary'], static function (mixed $value): void {
            if (!\is_string($value)) {
                throw new ConstraintDefinitionException(sprintf('The parameter "dictionary" of the "%s" constraint must be a list of strings.', static::class));
            }
        });
        $options['dictionary'] = $dictionary;
        parent::__construct($options, $groups, $payload);
    }
}
