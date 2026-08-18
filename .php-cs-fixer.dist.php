<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!file_exists(__DIR__.'/src')) {
    exit(0);
}

$fileHeaderParts = [
    <<<'EOF'
        This file is part of the Symfony package.

        (c) Fabien Potencier <fabien@symfony.com>

        EOF,
    <<<'EOF'

        For the full copyright and license information, please view the LICENSE
        file that was distributed with this source code.
        EOF,
];

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@PHP8x1Migration' => true, // take lowest version from `git grep -h '"php"' **/composer.json | uniq | sort`
        '@PHP8x1Migration:risky' => true,
        '@PHPUnit9x1Migration:risky' => true, // take version from src/Symfony/Bridge/PhpUnit/phpunit.xml.dist#L4
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'header_comment' => [
            'header' => implode('', $fileHeaderParts),
            'validator' => implode('', [
                '/',
                preg_quote($fileHeaderParts[0], '/'),
                '(?P<EXTRA>.*)??',
                preg_quote($fileHeaderParts[1], '/'),
                '/s',
            ]),
        ],
        'modern_serialization_methods' => false,
        'void_return' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__.'/src')
            ->append([__FILE__])
            ->notPath('#/Fixtures/#')
            ->exclude([
                'Symfony/Bridge/PhpUnit/',
                'Symfony/Component/Emoji/Resources/',
                'Symfony/Component/Intl/Resources/data/',
                'Symfony/Component/String/Resources/data/',
            ])
            // Support for older PHPunit version
            ->notPath('#Symfony/Bridge/PhpUnit/.*Mock\.php#')
            ->notPath('#Symfony/Bridge/PhpUnit/.*Legacy#')
            // auto-generated proxies
            ->notPath('#Symfony/Component/Cache/Traits/Re.*Proxy\.php#')
            // svg
            ->notPath('Symfony/Component/ErrorHandler/Resources/assets/images/symfony-ghost.svg.php')
            // HTML templates
            ->notPath('#Symfony/.*\.html\.php#')
    )
;
