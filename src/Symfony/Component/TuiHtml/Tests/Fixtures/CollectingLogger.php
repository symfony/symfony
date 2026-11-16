<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\TuiHtml\Tests\Fixtures;

use Psr\Log\AbstractLogger;
final class CollectingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $messages = [];
    public function log($level, $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }
    public function has(string $needle): bool
    {
        foreach ($this->messages as $message) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }
        return false;
    }
}
