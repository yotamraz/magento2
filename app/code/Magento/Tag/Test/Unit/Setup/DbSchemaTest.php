<?php
/**
 * Ported from Mage_Tag (magento-lts).
 * Original notice: Open Software License (OSL 3.0) / Academic Free License (AFL 3.0).
 * For copyright and license information, see LICENSE.txt and LICENSE_AFL.txt.
 */

declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Setup;

use PHPUnit\Framework\TestCase;

/**
 * Characterization of etc/db_schema.xml against legacy Mage_Tag sql/tag_setup/install-1.6.0.0.php
 * (expected values below are transcribed from that script; types: int/smallint/varchar/timestamp).
 */
class DbSchemaTest extends TestCase
{
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * Legacy columns: name => [type, unsigned, nullable, identity, default]
     * (null default means no default declared).
     */
    private const LEGACY_COLUMNS = [
        'tag' => [
            'tag_id' => ['int', true, false, true, null],
            'name' => ['varchar', null, true, false, null],
            'status' => ['smallint', false, false, false, '0'],
            'first_customer_id' => ['int', true, true, false, null],
            'first_store_id' => ['smallint', true, true, false, null],
        ],
        'tag_relation' => [
            'tag_relation_id' => ['int', true, false, true, null],
            'tag_id' => ['int', true, false, false, '0'],
            'customer_id' => ['int', true, true, false, null],
            'product_id' => ['int', true, false, false, '0'],
            'store_id' => ['smallint', true, false, false, '1'],
            'active' => ['smallint', true, false, false, '1'],
            'created_at' => ['timestamp', null, true, false, null],
        ],
        'tag_summary' => [
            'tag_id' => ['int', true, false, false, '0'],
            'store_id' => ['smallint', true, false, false, '0'],
            'customers' => ['int', true, false, false, '0'],
            'products' => ['int', true, false, false, '0'],
            'uses' => ['int', true, false, false, '0'],
            'historical_uses' => ['int', true, false, false, '0'],
            'popularity' => ['int', true, false, false, '0'],
            'base_popularity' => ['int', true, false, false, '0'],
        ],
        'tag_properties' => [
            'tag_id' => ['int', true, false, false, '0'],
            'store_id' => ['smallint', true, false, false, '0'],
            'base_popularity' => ['int', true, false, false, '0'],
        ],
    ];

    private const LEGACY_PRIMARY = [
        'tag' => ['tag_id'],
        'tag_relation' => ['tag_relation_id'],
        'tag_summary' => ['tag_id', 'store_id'],
        'tag_properties' => ['tag_id', 'store_id'],
    ];

    /**
     * Legacy foreign keys: table => [column => [refTable, refColumn, onDelete]]
     */
    private const LEGACY_FKS = [
        'tag' => [
            'first_customer_id' => ['customer_entity', 'entity_id', 'SET NULL'],
            'first_store_id' => ['store', 'store_id', 'SET NULL'],
        ],
        'tag_relation' => [
            'customer_id' => ['customer_entity', 'entity_id', 'CASCADE'],
            'product_id' => ['catalog_product_entity', 'entity_id', 'CASCADE'],
            'store_id' => ['store', 'store_id', 'CASCADE'],
            'tag_id' => ['tag', 'tag_id', 'CASCADE'],
        ],
        'tag_summary' => [
            'store_id' => ['store', 'store_id', 'CASCADE'],
            'tag_id' => ['tag', 'tag_id', 'CASCADE'],
        ],
        'tag_properties' => [
            'store_id' => ['store', 'store_id', 'CASCADE'],
            'tag_id' => ['tag', 'tag_id', 'CASCADE'],
        ],
    ];

    /**
     * Legacy explicit single/multi column indexes (non FK-auto), as column lists
     */
    private const LEGACY_INDEXES = [
        'tag' => [],
        'tag_relation' => [['product_id'], ['tag_id'], ['customer_id'], ['store_id']],
        'tag_summary' => [['store_id'], ['tag_id']],
        'tag_properties' => [['store_id']],
    ];

    private const LEGACY_UNIQUE = [
        'tag_relation' => [['tag_id', 'customer_id', 'product_id', 'store_id']],
    ];

    /**
     * @var \SimpleXMLElement
     */
    private $schema;

    protected function setUp(): void
    {
        $this->schema = simplexml_load_file(dirname(__DIR__, 3) . '/etc/db_schema.xml');
    }

    private function table(string $name): \SimpleXMLElement
    {
        $result = $this->schema->xpath('table[@name="' . $name . '"]');
        $this->assertCount(1, $result, "Table $name missing");
        return $result[0];
    }

    private function columnNames(\SimpleXMLElement $nodes): array
    {
        $names = [];
        foreach ($nodes as $column) {
            $names[] = (string)$column['name'];
        }
        return $names;
    }

    public function testOnlyLegacyTablesAreDeclared(): void
    {
        $names = [];
        foreach ($this->schema->table as $table) {
            $names[] = (string)$table['name'];
        }
        $this->assertEqualsCanonicalizing(array_keys(self::LEGACY_COLUMNS), $names);
    }

    public function testColumnsMatchLegacyInstallScript(): void
    {
        foreach (self::LEGACY_COLUMNS as $tableName => $columns) {
            $table = $this->table($tableName);
            $this->assertSame(
                array_keys($columns),
                $this->columnNames($table->column),
                "Column list/order differs for $tableName"
            );
            foreach ($table->column as $column) {
                $name = (string)$column['name'];
                [$type, $unsigned, $nullable, $identity, $default] = $columns[$name];
                $attrs = $column->attributes(self::XSI);
                $this->assertSame($type, (string)$attrs['type'], "$tableName.$name type");
                if ($unsigned !== null) {
                    $this->assertSame($unsigned, (string)$column['unsigned'] === 'true', "$tableName.$name unsigned");
                }
                $this->assertSame($nullable, (string)$column['nullable'] === 'true', "$tableName.$name nullable");
                $this->assertSame($identity, (string)$column['identity'] === 'true', "$tableName.$name identity");
                $actualDefault = isset($column['default']) ? (string)$column['default'] : null;
                $this->assertSame($default, $actualDefault, "$tableName.$name default");
            }
        }
        $this->assertSame('255', (string)$this->table('tag')->xpath('column[@name="name"]')[0]['length']);
    }

    public function testPrimaryKeysMatchLegacy(): void
    {
        foreach (self::LEGACY_PRIMARY as $tableName => $columns) {
            $primary = [];
            foreach ($this->table($tableName)->constraint as $constraint) {
                if ((string)$constraint->attributes(self::XSI)['type'] === 'primary') {
                    $primary[] = $this->columnNames($constraint->column);
                }
            }
            $this->assertSame([$columns], $primary, $tableName);
        }
    }

    public function testForeignKeysMatchLegacy(): void
    {
        foreach (self::LEGACY_FKS as $tableName => $fks) {
            $actual = [];
            foreach ($this->table($tableName)->constraint as $constraint) {
                if ((string)$constraint->attributes(self::XSI)['type'] === 'foreign') {
                    $actual[(string)$constraint['column']] = [
                        (string)$constraint['referenceTable'],
                        (string)$constraint['referenceColumn'],
                        (string)$constraint['onDelete'],
                    ];
                }
            }
            ksort($actual);
            ksort($fks);
            $this->assertSame($fks, $actual, "Foreign keys differ for $tableName");
        }
    }

    public function testIndexesAndUniqueConstraintsMatchLegacy(): void
    {
        foreach (self::LEGACY_INDEXES as $tableName => $indexes) {
            $actual = [];
            foreach ($this->table($tableName)->index as $index) {
                $actual[] = $this->columnNames($index->column);
            }
            $this->assertEqualsCanonicalizing($indexes, $actual, "Indexes differ for $tableName");

            $actualUnique = [];
            foreach ($this->table($tableName)->constraint as $constraint) {
                if ((string)$constraint->attributes(self::XSI)['type'] === 'unique') {
                    $actualUnique[] = $this->columnNames($constraint->column);
                }
            }
            $this->assertSame(self::LEGACY_UNIQUE[$tableName] ?? [], $actualUnique, "Unique differs for $tableName");
        }
    }

    public function testWhitelistCoversEveryDeclaration(): void
    {
        $whitelist = json_decode(
            file_get_contents(dirname(__DIR__, 3) . '/etc/db_schema_whitelist.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertEqualsCanonicalizing(array_keys(self::LEGACY_COLUMNS), array_keys($whitelist));
        foreach ($this->schema->table as $table) {
            $name = (string)$table['name'];
            $this->assertEqualsCanonicalizing(
                $this->columnNames($table->column),
                array_keys($whitelist[$name]['column']),
                "$name columns"
            );
            $constraints = [];
            foreach ($table->constraint as $constraint) {
                $constraints[] = (string)$constraint['referenceId'];
            }
            $this->assertEqualsCanonicalizing($constraints, array_keys($whitelist[$name]['constraint']), "$name constraints");
            $indexes = [];
            foreach ($table->index as $index) {
                $indexes[] = (string)$index['referenceId'];
            }
            $this->assertEqualsCanonicalizing($indexes, array_keys($whitelist[$name]['index'] ?? []), "$name indexes");
        }
    }
}
