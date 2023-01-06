<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LinkedIn\Share;

use Symfony\Component\Notifier\Exception\LogicException;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 *
 * @see https://docs.microsoft.com/en-us/linkedin/marketing/integrations/community-management/shares/ugc-post-api#sharemedia
 */
class ShareMediaShare extends AbstractLinkedInShare
{
    public const LEARN_MORE = 'LEARN_MORE';
    public const APPLY_NOW = 'APPLY_NOW ';
    public const DOWNLOAD = 'DOWNLOAD';
    public const GET_QUOTE = 'GET_QUOTE';
    public const SIGN_UP = 'SIGN_UP';
    public const SUBSCRIBE = 'SUBSCRIBE ';
    public const REGISTER = 'REGISTER';

    public const ALL = [
        self::LEARN_MORE,
        self::APPLY_NOW,
        self::DOWNLOAD,
        self::GET_QUOTE,
        self::SIGN_UP,
        self::SUBSCRIBE,
        self::REGISTER,
    ];

    public function __construct(string $text, array $attributes = [], string $inferredLocale = null, bool $landingPage = false, string $landingPageTitle = null, string $landingPageUrl = null)
    {
        $this->options['description'] = [
            'text' => $text,
            'attributes' => $attributes,
        ];

        if ($inferredLocale) {
            $this->options['description']['inferredLocale'] = $inferredLocale;
        }

        if ($landingPage || $landingPageUrl) {
            $this->options['landingPage']['landingPageUrl'] = $landingPageUrl;
        }

        if (null !== $landingPageTitle) {
            if (!\in_array($landingPageTitle, self::ALL)) {
                throw new LogicException(sprintf('"%s" is not valid option, available options are "%s".', $landingPageTitle, implode(', ', self::ALL)));
            }

            $this->options['landingPage']['landingPageTitle'] = $landingPageTitle;
        }
    }
}
