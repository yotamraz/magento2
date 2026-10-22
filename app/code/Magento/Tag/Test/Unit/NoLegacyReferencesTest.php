<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Static gate: the module must not reference legacy static access or legacy Zend classes
 */
class NoLegacyReferencesTest extends TestCase
{
    public function testNoLegacyReferences(): void
    {
        $forbidden = ['Mage' . '::', 'Zend' . '_', 'Varien' . '_'];
        $root = dirname(__DIR__, 2);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $violations = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            foreach ($forbidden as $needle) {
                if (str_contains($content, $needle)) {
                    $violations[] = $file->getPathname() . ' contains ' . $needle;
                }
            }
        }
        $this->assertSame([], $violations);
    }
}
