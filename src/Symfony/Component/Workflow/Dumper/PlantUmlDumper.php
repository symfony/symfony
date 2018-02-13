<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Dumper;

use InvalidArgumentException;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Marking;

/**
 * PlantUmlDumper dumps a workflow as a PlantUML file.
 *
 * You can convert the generated puml file with the plantuml.jar utility (http://plantuml.com/):
 *
 * php bin/console workflow:dump pull_request travis --dump-format=puml | java -jar plantuml.jar -p  > workflow.png
 *
 * @author Sébastien Morel <morel.seb@gmail.com>
 */
class PlantUmlDumper implements DumperInterface
{
    private const INITIAL = '<<initial>>';
    private const MARKED = '<<marked>>';

    const STATEMACHINE_TRANSITION = 'arrow';
    const WORKFLOW_TRANSITION = 'square';
    const TRANSITION_TYPES = array(self::STATEMACHINE_TRANSITION, self::WORKFLOW_TRANSITION);
    const DEFAULT_OPTIONS = array(
        'skinparams' => array(
            'titleBorderRoundCorner' => 15,
            'titleBorderThickness' => 2,
            'state' => array(
                'BackgroundColor'.self::INITIAL => '#87b741',
                'BackgroundColor'.self::MARKED => '#3887C6',
                'BorderColor' => '#3887C6',
                'BorderColor'.self::MARKED => 'Black',
                'FontColor'.self::MARKED => 'White',
            ),
            'agent' => array(
                'BackgroundColor' => '#ffffff',
                'BorderColor' => '#3887C6',
            ),
        ),
    );

    private $transitionType = self::STATEMACHINE_TRANSITION;

    public function __construct(string $transitionType = null)
    {
        if (!\in_array($transitionType, self::TRANSITION_TYPES, true)) {
            throw new InvalidArgumentException("Transition type '$transitionType' does not exist.");
        }
        $this->transitionType = $transitionType;
    }

    public function dump(Definition $definition, Marking $marking = null, array $options = array()): string
    {
        $options = array_replace_recursive(self::DEFAULT_OPTIONS, $options);
        $code = $this->initialize($options);
        foreach ($definition->getPlaces() as $place) {
            $placeEscaped = $this->escape($place);
            $code[] =
                "state $placeEscaped".
                ($definition->getInitialPlace() === $place ? ' '.self::INITIAL : '').
                ($marking && $marking->has($place) ? ' '.self::MARKED : '');
        }
        if ($this->isWorkflowTransitionType()) {
            foreach ($definition->getTransitions() as $transition) {
                $transitionEscaped = $this->escape($transition->getName());
                $code[] = "agent $transitionEscaped";
            }
        }
        foreach ($definition->getTransitions() as $transition) {
            $transitionEscaped = $this->escape($transition->getName());
            foreach ($transition->getFroms() as $from) {
                $fromEscaped = $this->escape($from);
                foreach ($transition->getTos() as $to) {
                    $toEscaped = $this->escape($to);
                    if ($this->isWorkflowTransitionType()) {
                        $lines = array(
                            "$fromEscaped --> $transitionEscaped",
                            "$transitionEscaped --> $toEscaped",
                        );
                        foreach ($lines as $line) {
                            if (!in_array($line, $code)) {
                                $code[] = $line;
                            }
                        }
                    } else {
                        $code[] = "$fromEscaped --> $toEscaped: $transitionEscaped";
                    }
                }
            }
        }

        return $this->startPuml($options).$this->getLines($code).$this->endPuml($options);
    }

    private function isWorkflowTransitionType(): bool
    {
        return self::WORKFLOW_TRANSITION === $this->transitionType;
    }

    private function startPuml(array $options): string
    {
        $start = '@startuml'.PHP_EOL;
        $start .= 'allow_mixing'.PHP_EOL;

        return $start;
    }

    private function endPuml(array $options): string
    {
        return PHP_EOL.'@enduml';
    }

    private function getLines(array $code): string
    {
        return implode(PHP_EOL, $code);
    }

    private function initialize(array $options): array
    {
        $code = array();
        if (isset($options['title'])) {
            $code[] = "title {$options['title']}";
        }
        if (isset($options['name'])) {
            $code[] = "title {$options['name']}";
        }
        if (isset($options['skinparams']) && is_array($options['skinparams'])) {
            foreach ($options['skinparams'] as $skinparamKey => $skinparamValue) {
                if (!$this->isWorkflowTransitionType() && 'agent' === $skinparamKey) {
                    continue;
                }
                if (!is_array($skinparamValue)) {
                    $code[] = "skinparam {$skinparamKey} $skinparamValue";
                    continue;
                }
                $code[] = "skinparam {$skinparamKey} {";
                foreach ($skinparamValue as $key => $value) {
                    $code[] = "    {$key} $value";
                }
                $code[] = '}';
            }
        }

        return $code;
    }

    private function escape(string $string): string
    {
        // It's not possible to escape property double quote, so let's remove it
        return '"'.str_replace('"', '', $string).'"';
    }
}
