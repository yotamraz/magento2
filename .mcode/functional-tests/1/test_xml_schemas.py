"""
Test XML configuration files for the Magento_Tag module.

Validates the structure of module.xml and db_schema.xml to ensure they
conform to Magento 2 standards and contain the required elements.
"""
import os
import xml.etree.ElementTree as ET
import pytest

MAGENTO_ROOT = os.environ.get('MAGENTO_ROOT', os.path.join(os.path.dirname(__file__), '../../..'))
MODULE_PATH = os.path.join(MAGENTO_ROOT, 'app/code/Magento/Tag')


class TestModuleXml:
    """Test etc/module.xml structure and content."""

    def test_module_xml_is_valid_xml(self):
        """Verify module.xml is valid XML."""
        module_xml = os.path.join(MODULE_PATH, 'etc/module.xml')
        tree = ET.parse(module_xml)
        assert tree.getroot() is not None, "module.xml root element is None"

    def test_module_name_declared(self):
        """Verify module name is Magento_Tag."""
        module_xml = os.path.join(MODULE_PATH, 'etc/module.xml')
        tree = ET.parse(module_xml)
        root = tree.getroot()
        module_elem = root.find('.//module[@name="Magento_Tag"]')
        assert module_elem is not None, "Module element with name='Magento_Tag' not found"

    def test_required_sequence_modules(self):
        """Verify module sequence includes required dependencies."""
        module_xml = os.path.join(MODULE_PATH, 'etc/module.xml')
        tree = ET.parse(module_xml)
        root = tree.getroot()

        # Get all sequence module names
        sequence_modules = [elem.get('name') for elem in root.findall('.//sequence/module')]

        # Required modules for Tag functionality
        required = ['Magento_Catalog', 'Magento_Customer', 'Magento_Store', 'Magento_Eav']

        for required_module in required:
            assert required_module in sequence_modules, \
                f"Required module '{required_module}' not in sequence. Found: {sequence_modules}"


class TestDbSchemaXml:
    """Test etc/db_schema.xml structure and table definitions."""

    def test_db_schema_is_valid_xml(self):
        """Verify db_schema.xml is valid XML."""
        db_schema = os.path.join(MODULE_PATH, 'etc/db_schema.xml')
        tree = ET.parse(db_schema)
        assert tree.getroot() is not None, "db_schema.xml root element is None"

    def test_all_four_tables_defined(self):
        """Verify all four tag tables are defined."""
        db_schema = os.path.join(MODULE_PATH, 'etc/db_schema.xml')
        tree = ET.parse(db_schema)
        root = tree.getroot()

        table_names = [elem.get('name') for elem in root.findall('.//table')]

        expected_tables = ['tag', 'tag_relation', 'tag_summary', 'tag_properties']
        for expected_table in expected_tables:
            assert expected_table in table_names, \
                f"Table '{expected_table}' not found in db_schema.xml. Found tables: {table_names}"

    def test_tag_table_has_primary_key(self):
        """Verify tag table has primary key on tag_id."""
        db_schema = os.path.join(MODULE_PATH, 'etc/db_schema.xml')
        tree = ET.parse(db_schema)
        root = tree.getroot()

        tag_table = root.find('.//table[@name="tag"]')
        assert tag_table is not None, "tag table not found"

        # Check for primary key constraint
        primary_key = tag_table.find('.//constraint[@xsi:type="primary"]',
                                     namespaces={'xsi': 'http://www.w3.org/2001/XMLSchema-instance'})
        assert primary_key is not None, "Primary key constraint not found on tag table"

        # Verify it's on tag_id column
        column = primary_key.find('column[@name="tag_id"]')
        assert column is not None, "Primary key not on tag_id column"

    def test_tag_relation_table_has_composite_unique_index(self):
        """Verify tag_relation has composite unique constraint."""
        db_schema = os.path.join(MODULE_PATH, 'etc/db_schema.xml')
        tree = ET.parse(db_schema)
        root = tree.getroot()

        tag_relation_table = root.find('.//table[@name="tag_relation"]')
        assert tag_relation_table is not None, "tag_relation table not found"

        # Find unique constraint with all four columns
        unique_constraints = tag_relation_table.findall('.//constraint[@xsi:type="unique"]',
                                                        namespaces={'xsi': 'http://www.w3.org/2001/XMLSchema-instance'})

        found_composite = False
        for constraint in unique_constraints:
            columns = [col.get('name') for col in constraint.findall('column')]
            if set(columns) >= {'tag_id', 'customer_id', 'product_id', 'store_id'}:
                found_composite = True
                break

        assert found_composite, \
            "Composite unique constraint on (tag_id, customer_id, product_id, store_id) not found"

    def test_tag_relation_foreign_keys(self):
        """Verify tag_relation has foreign keys to tag, product, customer, store."""
        db_schema = os.path.join(MODULE_PATH, 'etc/db_schema.xml')
        tree = ET.parse(db_schema)
        root = tree.getroot()

        tag_relation_table = root.find('.//table[@name="tag_relation"]')
        assert tag_relation_table is not None, "tag_relation table not found"

        foreign_keys = tag_relation_table.findall('.//constraint[@xsi:type="foreign"]',
                                                   namespaces={'xsi': 'http://www.w3.org/2001/XMLSchema-instance'})

        fk_references = [(fk.get('column'), fk.get('referenceTable')) for fk in foreign_keys]

        # Check for required foreign keys
        required_fks = [
            ('tag_id', 'tag'),
            ('product_id', 'catalog_product_entity'),
            ('customer_id', 'customer_entity'),
            ('store_id', 'store')
        ]

        for column, ref_table in required_fks:
            assert any(fk[0] == column and fk[1] == ref_table for fk in fk_references), \
                f"Foreign key from {column} to {ref_table} not found. Found FKs: {fk_references}"

    def test_tag_summary_composite_primary_key(self):
        """Verify tag_summary has composite primary key (tag_id, store_id)."""
        db_schema = os.path.join(MODULE_PATH, 'etc/db_schema.xml')
        tree = ET.parse(db_schema)
        root = tree.getroot()

        tag_summary_table = root.find('.//table[@name="tag_summary"]')
        assert tag_summary_table is not None, "tag_summary table not found"

        primary_key = tag_summary_table.find('.//constraint[@xsi:type="primary"]',
                                             namespaces={'xsi': 'http://www.w3.org/2001/XMLSchema-instance'})
        assert primary_key is not None, "Primary key not found on tag_summary"

        pk_columns = [col.get('name') for col in primary_key.findall('column')]
        assert set(pk_columns) == {'tag_id', 'store_id'}, \
            f"Expected composite PK (tag_id, store_id), got: {pk_columns}"
