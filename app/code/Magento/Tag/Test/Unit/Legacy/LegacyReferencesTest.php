<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Legacy;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Grep gate: the ported module must not reference legacy static access or legacy library classes
 */
class LegacyReferencesTest extends TestCase
{
    public function testNoLegacyReferencesInModule(): void
    {
        $moduleDir = dirname(__DIR__, 3);
        // Built from fragments so this file does not match its own patterns.
        $pattern = '/\b' . 'Ma' . 'ge::|\b' . 'Zen' . 'd_|\b' . 'Va' . 'rien_|\b' . 'Ma' . 'ge_/';

        $offenders = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!preg_match('/\.(php|phtml|xml|json|csv)$/', $file->getFilename())
                || $file->getRealPath() === __FILE__
            ) {
                continue;
            }
            $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES);
            foreach ($lines as $number => $line) {
                if (preg_match($pattern, $line)) {
                    $offenders[] = $file->getRealPath() . ':' . ($number + 1) . ': ' . trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, "Legacy references found:\n" . implode("\n", $offenders));
    }
}
