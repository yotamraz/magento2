<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Static grep gate: no legacy Mage:: static access or Zend_ classes inside Magento_Tag.
 */
class NoLegacyApiTest extends TestCase
{
    public function testModuleHasNoLegacyReferences(): void
    {
        $moduleDir = dirname(__DIR__, 2);
        $self = realpath(__FILE__);
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($moduleDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || $file->getRealPath() === $self
                || !in_array($file->getExtension(), ['php', 'xml', 'phtml', 'json', 'csv'], true)
            ) {
                continue;
            }
            $contents = (string)file_get_contents($file->getPathname());
            if (preg_match('/\bMage::|\bZend_[A-Za-z]|\bVarien_|\bMage_[A-Z]/', $contents)) {
                // Original-notice headers mention "Mage_Tag" as provenance only; ignore those lines.
                foreach (preg_split('/\R/', $contents) as $line) {
                    if (preg_match('/\bMage::|\bZend_[A-Za-z]|\bVarien_|\bMage_[A-Z]/', $line)
                        && !preg_match('/Ported from Mage_Tag|install-1\.6\.0\.0|legacy Mage_/i', $line)
                    ) {
                        $offenders[] = $file->getPathname() . ': ' . trim($line);
                    }
                }
            }
        }
        $this->assertSame([], $offenders, "Legacy API references found:\n" . implode("\n", $offenders));
    }
}
