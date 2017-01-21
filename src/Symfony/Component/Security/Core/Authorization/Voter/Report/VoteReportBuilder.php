<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter\Report;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Result\VoterResultInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Maxime Perrimond <max.perrimond@gmail.com>
 */
final class VoteReportBuilder implements VoteReportBuilderInterface
{
    /**
     * @var VoteReportCollectorInterface
     */
    private $collector;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var null|string
     */
    private $translationDomain;

    /**
     * @param VoteReportCollectorInterface $collector
     * @param TranslatorInterface          $translator
     * @param string|null                  $translationDomain
     */
    public function __construct(VoteReportCollectorInterface $collector, TranslatorInterface $translator, $translationDomain = null)
    {
        $this->collector = $collector;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
    }

    /**
     * {@inheritdoc}
     */
    public function addReport(VoterInterface $voter, VoterResultInterface $voterResult, $subject, TokenInterface $token)
    {
        if (null === $voterResult->getPlural()) {
            $translatedMessage = $this->translator->trans(
                $voterResult->getMessage(),
                $voterResult->getParameters(),
                $this->translationDomain
            );
        } else {
            try {
                $translatedMessage = $this->translator->transChoice(
                    $voterResult->getMessage(),
                    $voterResult->getPlural(),
                    $voterResult->getParameters(),
                    $this->translationDomain
                );
            } catch (\InvalidArgumentException $e) {
                $translatedMessage = $this->translator->trans(
                    $voterResult->getMessage(),
                    $voterResult->getParameters(),
                    $this->translationDomain
                );
            }
        }

        $this->collector->add(new VoteReport(
            $translatedMessage,
            $voter,
            $voterResult->getAttribute(),
            $subject,
            $token
        ));
    }
}
