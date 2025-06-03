<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Trigger;

use Cron\CronExpression;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Symfony\Component\Scheduler\Exception\LogicException;

/**
 * Use cron expressions to describe a periodical trigger.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class CronExpressionTrigger implements TriggerInterface
{
    private const HASH_ALIAS_MAP = [
        '#hourly' => '# * * * *',
        '#daily' => '# # * * *',
        '#weekly' => '# # * * #',
        '#weekly@midnight' => '# #(0-2) * * #',
        '#monthly' => '# # # * *',
        '#monthly@midnight' => '# #(0-2) # * *',
        '#annually' => '# # # # *',
        '#annually@midnight' => '# #(0-2) # # *',
        '#yearly' => '# # # # *',
        '#yearly@midnight' => '# #(0-2) # # *',
        '#midnight' => '# #(0-2) * * *',
    ];
    private const HASH_RANGES = [
        [0, 59],
        [0, 23],
        [1, 28],
        [1, 12],
        [0, 6],
    ];

    private readonly ?string $timezone;

    public function __construct(
        private readonly CronExpression $expression = new CronExpression('* * * * *'),
        \DateTimeZone|string|null $timezone = null,
    ) {
        $this->timezone = $timezone instanceof \DateTimeZone ? $timezone->getName() : $timezone;
    }

    public function __toString(): string
    {
        return $this->expression->getExpression();
    }

    public static function fromSpec(string $expression = '* * * * *', ?string $context = null, \DateTimeZone|string|null $timezone = null): self
    {
        if (!class_exists(CronExpression::class)) {
            throw new LogicException(\sprintf('You cannot use "%s" as the "cron expression" package is not installed. Try running "composer require dragonmantank/cron-expression".', __CLASS__));
        }

        if (!str_contains($expression, '#')) {
            return new self(new CronExpression($expression), $timezone);
        }

        if (null === $context) {
            throw new LogicException('A context must be provided to use "hashed" cron expressions.');
        }

        return new self(new CronExpression(self::parseHashed($expression, $context)), $timezone);
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->expression->getNextRunDate($run, timeZone: $this->timezone));
    }

    private static function parseHashed(string $expression, string $context): string
    {
        $expression = self::HASH_ALIAS_MAP[$expression] ?? $expression;
        $parts = explode(' ', $expression);

        if (5 !== \count($parts)) {
            return $expression;
        }

        $randomizer = new Randomizer(new Xoshiro256StarStar(hash('sha256', $context, true)));

        foreach ($parts as $position => $part) {
            if (preg_match('#^\#(\((\d+)-(\d+)\))?$#', $part, $matches)) {
                $parts[$position] = $randomizer->getInt(
                    (int) ($matches[2] ?? self::HASH_RANGES[$position][0]),
                    (int) ($matches[3] ?? self::HASH_RANGES[$position][1]),
                );
            }
        }

        return implode(' ', $parts);
    }
}
