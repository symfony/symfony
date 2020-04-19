<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\EventListener;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage as NoSecurityExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Exception\LogicException;

/**
 * @author Tien Xuan Vo <tien.xuan.vo@gmail.com>
 */
class NoSecurityGuardListener extends GuardListener
{
    protected $configuration;
    protected $expressionLanguage;

    private const UNSUPPORTED_EXPRESSIONS = [
        'is_anonymous',
        'is_authenticated',
        'is_fully_authenticated',
        'is_granted',
        'is_remember_me',
    ];

    public function __construct(array $configuration, NoSecurityExpressionLanguage $expressionLanguage)
    {
        $this->configuration = $configuration;
        $this->expressionLanguage = $expressionLanguage;
    }

    protected function validateGuardExpression(GuardEvent $event, string $expression)
    {
        try {
            parent::validateGuardExpression($event, $expression);
        } catch (SyntaxError $exception) {
            foreach (self::UNSUPPORTED_EXPRESSIONS as $unsupportedExpression) {
                if (0 === strpos($exception->getMessage(), sprintf('The function "%s" does not exist', $unsupportedExpression))) {
                    throw new LogicException('Cannot validate guard expression as the SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');
                }
            }
            throw $exception;
        }
    }

    protected function getVariables(GuardEvent $event): array
    {
        return ['subject' => $event->getSubject()];
    }
}
