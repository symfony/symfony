<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Configures dump() handler.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpListener implements EventSubscriberInterface
{
    private $cloner;
    private $dumper;
    private $expressionLanguage;
    private $expression;

    /**
     * @param ClonerInterface         $cloner             Cloner service.
     * @param DataDumperInterface     $dumper             Dumper service.
     * @param ExpressionLanguage|null $expressionLanguage Expression Language service.
     * @param string|null             $expression         The expression.
     */
    public function __construct(ClonerInterface $cloner, DataDumperInterface $dumper, ExpressionLanguage $expressionLanguage = null, $expression = null)
    {
        if (null !== $expression && null === $expressionLanguage) {
            throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
        }

        $this->cloner = $cloner;
        $this->dumper = $dumper;
        $this->expressionLanguage = $expressionLanguage;
        $this->expression = $expression;
    }

    public function configure(GetResponseEvent $event)
    {
        if ($this->expression) {
            $result = $this->expressionLanguage->evaluate($this->expression, array('request' => $event->getRequest()));
            if ($result) {
                CliDumper::$defaultColors = true;
                CliDumper::$defaultOutput = 'php://output';

                return;
            }
        }

        $cloner = $this->cloner;
        $dumper = $this->dumper;

        VarDumper::setHandler(function ($var) use ($cloner, $dumper) {
            $dumper->dump($cloner->cloneVar($var));
        });
    }

    public static function getSubscribedEvents()
    {
        // Register early to have a working dump() as early as possible
        return array(KernelEvents::REQUEST => array('configure', 1024));
    }
}
