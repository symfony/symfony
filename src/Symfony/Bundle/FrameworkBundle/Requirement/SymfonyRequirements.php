<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Requirement;

/**
 * This class specifies all requirements and optional recommendations that
 * are necessary to run the Symfony Standard Edition.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
class SymfonyRequirements extends RequirementCollection
{
    const REQUIRED_PHP_VERSION = '5.3.3';

    /**
     * Constructor that initializes the requirements.
     */
    public function __construct()
    {
        /* mandatory requirements follow */

        $installedPhpVersion = phpversion();

        $this->addRequirement(
            version_compare($installedPhpVersion, self::REQUIRED_PHP_VERSION, '>='),
            sprintf('PHP version must be at least %s (%s installed)', self::REQUIRED_PHP_VERSION, $installedPhpVersion),
            sprintf('You are running PHP version "<strong>%s</strong>", but Symfony needs at least PHP "<strong>%s</strong>" to run.
                Before using Symfony, upgrade your PHP installation, preferably to the latest version.',
                $installedPhpVersion, self::REQUIRED_PHP_VERSION),
            sprintf('Install PHP %s or newer (installed version is %s)', self::REQUIRED_PHP_VERSION, $installedPhpVersion)
        );

        $this->addRequirement(
            is_dir(__DIR__.'/../vendor/composer'),
            'Vendor libraries must be installed',
            'Vendor libraries are missing. Install composer following instructions from <a href="http://getcomposer.org/">http://getcomposer.org/</a>. ' .
                'Then run "<strong>php composer.phar install</strong>" to install them.'
        );

        $baseDir = basename(__DIR__);
        $this->addRequirement(
            is_writable(__DIR__.'/cache'),
            "$baseDir/cache/ directory must be writable",
            "Change the permissions of the \"<strong>$baseDir/cache/</strong>\" directory so that the web server can write into it."
        );

        $this->addRequirement(
            is_writable(__DIR__.'/logs'),
            "$baseDir/logs/ directory must be writable",
            "Change the permissions of the \"<strong>$baseDir/logs/</strong>\" directory so that the web server can write into it."
        );

        $this->addPhpIniRequirement(
            'date.timezone', true, false,
            'date.timezone setting must be set',
            'Set the "<strong>date.timezone</strong>" setting in php.ini<a href="#phpini">*</a> (like Europe/Paris).'
        );

        if (version_compare($installedPhpVersion, self::REQUIRED_PHP_VERSION, '>=')) {
            $this->addRequirement(
                (in_array(date_default_timezone_get(), \DateTimeZone::listIdentifiers())),
                sprintf('Default timezone "%s" is not supported by your installation of PHP', date_default_timezone_get()),
                'Fix your <strong>php.ini</strong> file (check for typos and have a look at the list of deprecated timezones http://php.net/manual/en/timezones.others.php).'
            );
        }

        $this->addRequirement(
            function_exists('json_encode'),
            'json_encode() must be available',
            'Install and enable the <strong>JSON</strong> extension.'
        );

        $this->addRequirement(
            function_exists('session_start'),
            'session_start() must be available',
            'Install and enable the <strong>session</strong> extension.'
        );

        $this->addRequirement(
            function_exists('ctype_alpha'),
            'ctype_alpha() must be available',
            'Install and enable the <strong>ctype</strong> extension.'
        );

        $this->addRequirement(
            function_exists('token_get_all'),
            'token_get_all() must be available',
            'Install and enable the <strong>Tokenizer</strong> extension.'
        );

        $this->addRequirement(
            function_exists('simplexml_import_dom'),
            'simplexml_import_dom() must be available',
            'Install and enable the <strong>SimpleXML</strong> extension.'
        );

        $this->addRequirement(
            !(function_exists('apc_store') && ini_get('apc.enabled')) || version_compare(phpversion('apc'), '3.0.17', '>='),
            'APC version must be at least 3.0.17',
            'Upgrade your <strong>APC</strong> extension (3.0.17+)'
        );

        $this->addPhpIniRequirement('detect_unicode', false);

        $this->addPhpIniRequirement(
            'suhosin.executor.include.whitelist',
            create_function('$cfgValue', 'return false !== stripos($cfgValue, "phar");'),
            true,
            'suhosin.executor.include.whitelist must be configured correctly in php.ini',
            'Add "<strong>phar</strong>" to <strong>suhosin.executor.include.whitelist</strong> in php.ini<a href="#phpini">*</a>.'
        );

        if (extension_loaded('xdebug')) {
            $this->addPhpIniRequirement(
                'xdebug.show_exception_trace', false, true,
                'xdebug.show_exception_trace setting must be disabled',
                'Set the "<strong>xdebug.show_exception_trace</strong>" setting to "Off" in php.ini<a href="#phpini">*</a>.'
            );

            $this->addPhpIniRequirement(
                'xdebug.scream', false, true,
                'xdebug.scream setting must be disabled',
                'Set the "<strong>xdebug.scream</strong>" setting to "Off" in php.ini<a href="#phpini">*</a>.'
            );
        }

        $pcreVersion = defined('PCRE_VERSION') ? (float) PCRE_VERSION : null;

        $this->addRequirement(
            null !== $pcreVersion && $pcreVersion > 8.0,
            sprintf('PCRE extension must be available and at least 8.0 (%s installed)', $pcreVersion ? $pcreVersion : 'not'),
            'Upgrade your <strong>PCRE</strong> extension (8.0+)'
        );

        $this->addRequirement(
            version_compare($installedPhpVersion, '5.3.16', '!='),
            'Symfony won\'t work properly with PHP 5.3.16',
            'Install PHP 5.3.17 or newer'
        );

        /* optional recommendations follow */

        $this->addRecommendation(
            version_compare($installedPhpVersion, '5.3.4', '>='),
            sprintf('Your project might not work properly ("Notice: Trying to get property of non-object") due to the PHP bug #52083 before PHP 5.3.4 (%s installed)', $installedPhpVersion),
            'Install PHP 5.3.4 or newer'
        );

        $this->addRecommendation(
            version_compare($installedPhpVersion, '5.4.0', '!='),
            'Your project might not work properly ("Cannot dump definitions which have method calls") due to the PHP bug #61453 in PHP 5.4.0',
            'Install PHP 5.4.1 or newer'
        );

        $this->addRecommendation(
            version_compare($installedPhpVersion, '5.3.8', '>='),
            sprintf('Annotations might not work properly due to the PHP bug #55156 before PHP 5.3.8 (%s installed)', $installedPhpVersion),
            'Install PHP 5.3.8 or newer if your project uses annotations'
        );

        $this->addRecommendation(
            !(extension_loaded('intl') && null === new \Collator('fr_FR')),
            'intl extension should be correctly configured',
            'The intl extension does not behave properly. This problem is typical on PHP 5.3.X x64 WIN builds'
        );

        $this->addRecommendation(
            class_exists('DomDocument'),
            'PHP-XML module should be installed',
            'Install and enable the <strong>PHP-XML</strong> module.'
        );

        $this->addRecommendation(
            function_exists('mb_strlen'),
            'mb_strlen() should be available',
            'Install and enable the <strong>mbstring</strong> extension.'
        );

        $this->addRecommendation(
            function_exists('iconv'),
            'iconv() should be available',
            'Install and enable the <strong>iconv</strong> extension.'
        );

        $this->addRecommendation(
            function_exists('utf8_decode'),
            'utf8_decode() should be available',
            'Install and enable the <strong>XML</strong> extension.'
        );

        if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->addRecommendation(
                function_exists('posix_isatty'),
                'posix_isatty() should be available',
                'Install and enable the <strong>php_posix</strong> extension (used to colorize the CLI output).'
            );
        }

        $this->addRecommendation(
            class_exists('Locale'),
            'intl extension should be available',
            'Install and enable the <strong>intl</strong> extension (used for validators).'
        );

        if (class_exists('Locale')) {
            if (defined('INTL_ICU_VERSION')) {
                $version = INTL_ICU_VERSION;
            } else {
                $reflector = new ReflectionExtension('intl');

                ob_start();
                $reflector->info();
                $output = strip_tags(ob_get_clean());

                preg_match('/^ICU version +(?:=> )?(.*)$/m', $output, $matches);
                $version = $matches[1];
            }

            $this->addRecommendation(
                version_compare($version, '4.0', '>='),
                'intl ICU version should be at least 4+',
                'Upgrade your <strong>intl</strong> extension with a newer ICU version (4+).'
            );
        }

        $accelerator =
            (function_exists('apc_store') && ini_get('apc.enabled'))
            ||
            function_exists('eaccelerator_put') && ini_get('eaccelerator.enable')
            ||
            function_exists('xcache_set')
        ;

        $this->addRecommendation(
            $accelerator,
            'a PHP accelerator should be installed',
            'Install and enable a <strong>PHP accelerator</strong> like APC (highly recommended).'
        );

        $this->addPhpIniRecommendation('short_open_tag', false);

        $this->addPhpIniRecommendation('magic_quotes_gpc', false, true);

        $this->addPhpIniRecommendation('register_globals', false, true);

        $this->addPhpIniRecommendation('session.auto_start', false);

        $this->addRecommendation(
            class_exists('PDO'),
            'PDO should be installed',
            'Install <strong>PDO</strong> (mandatory for Doctrine).'
        );

        if (class_exists('PDO')) {
            $drivers = \PDO::getAvailableDrivers();
            $this->addRecommendation(
                count($drivers),
                sprintf('PDO should have some drivers installed (currently available: %s)', count($drivers) ? implode(', ', $drivers) : 'none'),
                'Install <strong>PDO drivers</strong> (mandatory for Doctrine).'
            );
        }
    }
}
