<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The "Messenger" tab of the interactive "debug" command.
 *
 * Reuses the same data source (the bus/message/handler mapping built by the
 * MessengerPass) as the "debug:messenger" command, so the detail pane shows the
 * handlers of the selected message exactly like "debug:messenger" does.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class MessengerDebugSection extends AbstractDebugSection
{
    /**
     * @param array<string, array<string, list<array{0: string, 1: array<string, mixed>}>>> $mapping
     */
    public function __construct(
        private readonly array $mapping,
    ) {
    }

    public function getLabel(): string
    {
        return 'Messenger';
    }

    public function getShortLabel(): string
    {
        return 'Bus';
    }

    public function describe(DebugItem $item, int $width): string
    {
        [$bus, $message] = explode(self::VALUE_SEPARATOR, $item->value, 2);

        if (!isset($this->mapping[$bus][$message])) {
            return \sprintf('Message "%s" is not handled by bus "%s".', $message, $bus);
        }

        $handlers = $this->mapping[$bus][$message];

        return $this->describeToBuffer(function (SymfonyStyle $io) use ($bus, $message, $handlers): void {
            $io->section($bus);

            $tableRows = [];
            if ($description = self::getClassDescription($message)) {
                $tableRows[] = [\sprintf('<comment>%s</>', $description)];
            }

            $tableRows[] = [\sprintf('<fg=cyan>%s</fg=cyan>', $message)];
            foreach ($handlers as $handler) {
                $tableRows[] = [\sprintf('    handled by <info>%s</>', $handler[0]).$this->formatConditions($handler[1])];
                if ($handlerDescription = self::getClassDescription($handler[0])) {
                    $tableRows[] = [\sprintf('               <comment>%s</>', $handlerDescription)];
                }
            }

            $io->table([], $tableRows);
        });
    }

    /**
     * Builds the full, unfiltered item list once. Recomputing it on every keystroke
     * would be costly on large applications.
     *
     * @return list<DebugItem>
     */
    protected function buildItems(): array
    {
        $singleBus = 1 === \count($this->mapping);

        $items = [];
        foreach ($this->mapping as $bus => $handlersByMessage) {
            foreach ($handlersByMessage as $message => $handlers) {
                $label = $singleBus ? $message : \sprintf('%s (%s)', $message, $bus);
                $searchText = [];
                foreach ($handlers as $handler) {
                    $searchText[] = $handler[0];
                }

                $items[] = new DebugItem('message', $bus.self::VALUE_SEPARATOR.$message, $label, searchText: $searchText ? implode("\n", $searchText) : null);
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function formatConditions(array $options): string
    {
        if (!$options) {
            return '';
        }

        $optionsMapping = [];
        foreach ($options as $key => $value) {
            $optionsMapping[] = $key.'='.$value;
        }

        return ' (when '.implode(', ', $optionsMapping).')';
    }

    private static function getClassDescription(string $class): string
    {
        try {
            $r = new \ReflectionClass($class);

            if ($docComment = $r->getDocComment()) {
                $docComment = preg_split('#\n\s*\*\s*[\n@]#', substr($docComment, 3, -2), 2)[0];

                return trim(preg_replace('#\s*\n\s*\*\s*#', ' ', $docComment));
            }
        } catch (\ReflectionException) {
        }

        return '';
    }
}
