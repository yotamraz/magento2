<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Legacy;

use PHPUnit\Framework\TestCase;

/**
 * The ported module must not reference the legacy Mage:: facade or Zend_ library classes.
 */
class NoLegacyReferencesTest extends TestCase
{
    public function testModuleSourcesHaveNoLegacyReferences(): void
    {
        $moduleDir = dirname(__DIR__, 3);
        $testDir = $moduleDir . DIRECTORY_SEPARATOR . 'Test';
        $violations = [];
        $scanned = 0;

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $moduleDir,
            \FilesystemIterator::SKIP_DOTS
        ));
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || str_starts_with($file->getPathname(), $testDir)) {
                continue;
            }
            $scanned++;
            $content = (string)file_get_contents($file->getPathname());
            if (preg_match_all('/\bMage::|\bZend_/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$match, $offset]) {
                    $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                    $violations[] = sprintf('%s:%d (%s)', $file->getPathname(), $line, $match);
                }
            }
        }

        $this->assertGreaterThan(0, $scanned, 'No PHP sources were scanned');
        $this->assertSame([], $violations, "Legacy references found:\n" . implode("\n", $violations));
    }
}
