<?php

declare(strict_types=1);

use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Rector\Compiler\PhpScoper\StaticEasyPrefixer;
use Rector\Compiler\Unprefixer;
use Rector\Compiler\ValueObject\ScoperOption;
use Rector\Core\Application\VersionResolver;

require_once __DIR__ . '/vendor/autoload.php';

// remove phpstan, because it is already prefixed in its own scope
$dateTime = DateTime::from('now');
$timestamp = $dateTime->format('Ymd');
/**
 * @var array<string, string[]>
 */
const UNPREFIX_CLASSES_BY_FILE = [
    // make UT=1 in tests work
    'packages/Testing/PHPUnit/AbstractRectorTestCase.php' => [
        'PHPUnit\Framework\ExpectationFailedException',
        'PHPUnit\Framework\TestCase',
    ],

    // unprefixed ComposerJson as part of public API in ComposerRectorInterface
    'rules/Composer/Contract/Rector/ComposerRectorInterface.php' => [
        'Symplify\ComposerJsonManipulator\ValueObject\ComposerJson',
    ],
    'packages/Testing/PHPUnit/AbstractTestCase.php' => ['PHPUnit\Framework\TestCase'],
];

/**
 * @see https://regex101.com/r/LMDq0p/1
 * @var string
 */
const POLYFILL_FILE_NAME_REGEX = '#vendor\/symfony\/polyfill\-(.*)\/bootstrap(.*?)\.php#';

/**
 * @see https://regex101.com/r/RBZ0bN/1
 * @var string
 */
const POLYFILL_STUBS_NAME_REGEX = '#vendor\/symfony\/polyfill\-(.*)\/Resources\/stubs#';

// see https://github.com/humbug/php-scoper
return [
    ScoperOption::PREFIX => 'RectorPrefix' . $timestamp,
    ScoperOption::WHITELIST => StaticEasyPrefixer::getExcludedNamespacesAndClasses(),
    ScoperOption::PATCHERS => [
        function (string $filePath, string $prefix, string $content): string {
            foreach (UNPREFIX_CLASSES_BY_FILE as $endFilePath => $unprefixClasses) {
                if (! \str_ends_with($filePath, $endFilePath)) {
                    continue;
                }

                foreach ($unprefixClasses as $unprefixClass) {
                    $doubleQuotedClass = preg_quote('\\' . $unprefixClass);
                    $content = Strings::replace($content, '#' . $prefix . $doubleQuotedClass . '#', $unprefixClass);
                }
            }

            return $content;
        },


        function (string $filePath, string $prefix, string $content): string {
            if (! \str_ends_with($filePath, 'src/Application/VersionResolver.php')) {
                return $content;
            }

            $releaseDateTime = VersionResolver::resolverReleaseDateTime();

            return strtr(
                $content,
                [
                    '@package_version@' => VersionResolver::resolvePackageVersion(),
                    '@release_date@' => $releaseDateTime->format('Y-m-d H:i:s'),
                ]
            );
        },

        // unprefixed SmartFileInfo
        fn (string $filePath, string $prefix, string $content): string => Strings::replace(
            $content,
            '#' . $prefix . '\\\\Symplify\\\\SmartFileSystem\\\\SmartFileInfo#',
            'Symplify\SmartFileSystem\SmartFileInfo'
        ),

        // unprefixed Statement
        fn (string $filePath, string $prefix, string $content): string => Strings::replace(
            $content,
            '#' . $prefix . '\\\\Helmich\\\\TypoScriptParser\\\\Parser\\\\AST\\\\Statement#',
            'Helmich\TypoScriptParser\Parser\AST\Statement'
        ),

        // unprefixed Traverser
        fn (string $filePath, string $prefix, string $content): string => Strings::replace(
            $content,
            '#' . $prefix . '\\\\Helmich\\\\TypoScriptParser\\\\Parser\\\\Traverser\\\\Traverser#',
            'Helmich\TypoScriptParser\Parser\Traverser\Traverser'
        ),

        // unprefixed PHPUnit IsEqual
        fn (string $filePath, string $prefix, string $content): string => Strings::replace(
            $content,
            '#' . $prefix . '\\\\PHPUnit\\\\Framework\\\\Constraint\\\\IsEqual#',
            'PHPUnit\Framework\Constraint\IsEqual'
        ),

        // fixes https://github.com/rectorphp/rector/issues/7017
        function (string $filePath, string $prefix, string $content): string {
            if (str_ends_with($filePath, 'vendor/symfony/string/ByteString.php')) {
                return Strings::replace($content, '#' . $prefix . '\\\\\\\\1_\\\\\\\\2#', '\\\\1_\\\\2');
            }

            if (str_ends_with($filePath, 'vendor/symfony/string/AbstractUnicodeString.php')) {
                return Strings::replace($content, '#' . $prefix . '\\\\\\\\1_\\\\\\\\2#', '\\\\1_\\\\2');
            }

            return $content;
        },

        // unprefixed ContainerConfigurator
        function (string $filePath, string $prefix, string $content): string {
            // keep vendor prefixed the prefixed file loading; not part of public API
            // except @see https://github.com/symfony/symfony/commit/460b46f7302ec7319b8334a43809523363bfef39#diff-1cd56b329433fc34d950d6eeab9600752aa84a76cbe0693d3fab57fed0f547d3R110
            if (str_contains($filePath, 'vendor/symfony') && ! str_ends_with(
                $filePath,
                'vendor/symfony/dependency-injection/Loader/PhpFileLoader.php'
            )) {
                return $content;
            }

            return Strings::replace(
                $content,
                '#' . $prefix . '\\\\Symfony\\\\Component\\\\DependencyInjection\\\\Loader\\\\Configurator\\\\ContainerConfigurator#',
                'Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator'
            );
        },

        // get version for prefixed version
        function (string $filePath, string $prefix, string $content): string {
            if (! \str_ends_with($filePath, 'src/Configuration/Configuration.php')) {
                return $content;
            }

            // @see https://regex101.com/r/gLefQk/1
            return Strings::replace($content, '#\(\'rector\/rector-src\'\)#', "('rector/rector')");
        },

        // un-prefix composer plugin
        function (string $filePath, string $prefix, string $content): string {
            if (! \str_ends_with($filePath, 'vendor/rector/extension-installer/src/Plugin.php')) {
                return $content;
            }

            // see https://regex101.com/r/v8zRMm/1
            return Strings::replace($content, '#' . $prefix . '\\\\Composer\\\\#', 'Composer\\');
        },

        // fixes https://github.com/rectorphp/rector/issues/6007
        function (string $filePath, string $prefix, string $content): string {
            if (! \str_contains($filePath, 'vendor/')) {
                return $content;
            }

            // @see https://regex101.com/r/lBV8IO/2
            $fqcnReservedPattern = sprintf('#(\\\\)?%s\\\\(parent|self|static)#m', $prefix);
            $matches = Strings::matchAll($content, $fqcnReservedPattern);

            if ($matches === []) {
                return $content;
            }

            foreach ($matches as $match) {
                $content = str_replace($match[0], $match[2], $content);
            }

            return $content;
        },

        // unprefix string classes, as they're string on purpose - they have to be checked in original form, not prefixed
        function (string $filePath, string $prefix, string $content): string {
            // skip vendor, expect rector packages
            if (\str_contains($filePath, 'vendor/') && ! \str_contains($filePath, 'vendor/rector') && ! \str_contains(
                $filePath,
                'vendor/ssch/typo3-rector'
            )) {
                return $content;
            }

            // skip bin/rector.php for composer autoload class
            if (\str_ends_with($filePath, 'bin/rector.php')) {
                return $content;
            }

            return Unprefixer::unprefixQuoted($content, $prefix);
        },

        // scoper missed PSR-4 autodiscovery in Symfony
        function (string $filePath, string $prefix, string $content): string {
            // scoper missed PSR-4 autodiscovery in Symfony
            if (! \str_ends_with($filePath, 'config.php') && ! \str_ends_with($filePath, 'services.php')) {
                return $content;
            }

            // skip "Rector\\" namespace
            if (\str_contains($content, '$services->load(\'Rector')) {
                return $content;
            }

            // skip "Ssch\\" namespace
            if (\str_contains($content, '$services->load(\'Ssch')) {
                return $content;
            }

            return Strings::replace($content, '#services\->load\(\'#', "services->load('" . $prefix . '\\');
        },

        // unprefix polyfill functions
        // @see https://github.com/humbug/php-scoper/issues/440#issuecomment-795160132
        function (string $filePath, string $prefix, string $content): string {
            if (! Strings::match($filePath, POLYFILL_FILE_NAME_REGEX)) {
                return $content;
            }

            return Strings::replace($content, '#namespace ' . $prefix . ';#', '');
        },

        // remove namespace from polyfill stubs
        function (string $filePath, string $prefix, string $content): string {
            if (! Strings::match($filePath, POLYFILL_STUBS_NAME_REGEX)) {
                return $content;
            }

            // remove alias to class have origin PHP names - fix in
            $content = Strings::replace($content, '#\\\\class_alias(.*?);#', '');

            return Strings::replace($content, '#namespace ' . $prefix . ';#', '');
        },

    ],
];
