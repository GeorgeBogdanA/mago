<?php

declare(strict_types=1);

// Shared coding style for every PHP service in the monorepo.
// Run: tools/php-cs-fixer/vendor/bin/php-cs-fixer fix
// Also applied automatically by the Claude Code hook in .claude/hooks/php-cs-fixer.sh

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude(['tools', 'var', 'node_modules']); // vendor/ is excluded by default

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    // '@Symfony' is the Symfony coding standard (builds on PER-CS); ':risky' adds
    // behaviour-changing modernizations (e.g. compiler-optimized native function calls).
    // Single rules after the sets override them.
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP8x4Migration' => true,
        'declare_strict_types' => true,
        // Security: forbid loose comparisons (type juggling, e.g. "0e1" == "0e2").
        'strict_comparison' => true,
        'strict_param' => true,
    ])
    ->setCacheFile(__DIR__.'/.php-cs-fixer.cache')
    ->setFinder($finder);
