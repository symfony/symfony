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
    private const SYMFONY_LOGO = 'sprite $sf_logo [81x20/16z] {
hPNRaYiX24K1xwBo_tyx6-qaCtDEJ-KXLYMTLbp0HWcHZr3KRDJ8z94HG3jZn4_mijbQ2ryJoFePtXLWA_qxyGy19DpdY_10z11ZAbGjFHRwcEbcKx5-wqsV
yIMo8StMCHKh8ZUxnEwrZiwRAUOvy1lLcPQF4lEFAjhzMd5WOAqvKflS0Enx8PbihiSYXM8ClGVAseIWTAjCgVSAcnYbQG79xKFsZ0VnDCNc7AVBoPSMcTsX
UnrujbYjjz0NnsObkTgnmolqJD4QgGUYTQiNe8eIjtx4b6Vv8nPGpncn3NJ8Geo9W9VW2wGACm_JzgIO8A8KXr2jUBCVGEAAJSZ6JUlsNnmOzmIYti9G7bjL
8InaHM9G40NkwTG7OxrggvNIejA8AZuqyWjOzTIKi-wwYvjeHYesSWuPiTGDN5THzkYLU4MD5r2_0PDhG7LIUG33z5HtM6CP3icyWEVOS61sD_2ZsBfJdbVA
qM53XHDUwhY0TAwPug3OG9NonRFhO8ynF3I4unuAMDHmSrXH57V1RGvl9jafuZF9ZhqjWOEh98y0tUYGsUxkBSllIyBdT2oM5Fn2-ut-fzsq_cQNuL6Uvwqr
knh4RrvOKzxZfLV3s0rs_R_1SdYt3VxeQ1_y2_W2
}';
    private const INITIAL = 'initial';
    private const MARKED = 'marked';

    const STATEMACHINE_TRANSITION = 'arrow';
    const WORKFLOW_TRANSITION = 'square';
    const TRANSITION_TYPES = array(self::STATEMACHINE_TRANSITION, self::WORKFLOW_TRANSITION);
    const DEFAULT_OPTIONS = array(
        'skinparams' => array(
            'titleBorderRoundCorner' => 15,
            'titleBorderThickness' => 2,
            'state' => array(
                'BackgroundColor<<'.self::INITIAL.'>>' => '#87b741',
                'BackgroundColor<<'.self::MARKED.'>>' => '#3887C6',
                'BorderColor' => '#3887C6',
                'BorderColor<<'.self::MARKED.'>>' => 'Black',
                'FontColor<<'.self::MARKED.'>>' => 'White',
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
        if (!in_array($transitionType, self::TRANSITION_TYPES)) {
            throw new InvalidArgumentException("Transition type '{$transitionType}' does not exist.");
        }
        $this->transitionType = $transitionType;
    }

    public function dump(Definition $definition, Marking $marking = null, array $options = array()): string
    {
        $options = array_replace_recursive(self::DEFAULT_OPTIONS, $options);
        $code = $this->initialize($options);
        foreach ($definition->getPlaces() as $place) {
            $code[] =
                "state {$place}".
                ($definition->getInitialPlace() === $place ? ' <<'.self::INITIAL.'>>' : '').
                ($marking && $marking->has($place) ? ' <<'.self::MARKED.'>>' : '');
        }
        if ($this->isWorkflowTransitionType()) {
            foreach ($definition->getTransitions() as $transition) {
                $code[] = "agent {$transition->getName()}";
            }
        }
        foreach ($definition->getTransitions() as $transition) {
            foreach ($transition->getFroms() as $from) {
                foreach ($transition->getTos() as $to) {
                    if ($this->isWorkflowTransitionType()) {
                        $lines = array(
                            "{$from} --> {$transition->getName()}",
                            "{$transition->getName()} --> {$to}",
                        );
                        foreach ($lines as $line) {
                            if (!in_array($line, $code)) {
                                $code[] = $line;
                            }
                        }
                    } else {
                        $code[] = "{$from} --> {$to}: {$transition->getName()}";
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

        if ($this->isWorkflowTransitionType()) {
            $start .= 'allow_mixing'.PHP_EOL;
        }

        if ($options['nofooter'] ?? false) {
            return $start;
        }

        return $start.self::SYMFONY_LOGO.PHP_EOL;
    }

    private function endPuml(array $options): string
    {
        $end = PHP_EOL.'@enduml';
        if ($options['nofooter'] ?? false) {
            return $end;
        }

        return PHP_EOL.'footer \nGenerated by <$sf_logo> **Workflow Component** and **PlantUML**'.$end;
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
}
