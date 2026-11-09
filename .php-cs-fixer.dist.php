<?php

declare(strict_types=1);

// Shared coding style for every PHP service in the monorepo.
// Run: tools/php-cs-fixer/vendor/bin/php-cs-fixer fix
// Also applied automatically by the Claude Code hook in .claude/hooks/php-cs-fixer.sh

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['tools', 'var', 'node_modules']); // vendor/ is excluded by default

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    // TODO(human): choose the rule set for the project.
    // '@Symfony' is the Symfony coding standard (builds on PER-CS); '@PER-CS' alone is the
    // framework-neutral PHP-FIG style. Add single rules here to override the set.
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
    ])
    ->setCacheFile(__DIR__.'/.php-cs-fixer.cache')
    ->setFinder($finder);
