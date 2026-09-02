"""
Test PHP class autoloading for Magento_Tag module.

Verifies that all core PHP classes can be autoloaded via the vendor
autoloader without errors.
"""
import subprocess
import os
import pytest

MAGENTO_ROOT = os.environ.get('MAGENTO_ROOT', os.path.join(os.path.dirname(__file__), '../../..'))
PHP_BIN = os.environ.get('PHP_BIN', 'php')
PHP_INI = os.environ.get('PHP_INI', '')


def run_php(script_content):
    """Execute PHP code and return stdout, stderr, and return code."""
    php_args = [PHP_BIN]
    if PHP_INI:
        php_args.extend(['-c', PHP_INI])
    php_args.append('-r')
    php_args.append(script_content)

    result = subprocess.run(
        php_args,
        cwd=MAGENTO_ROOT,
        capture_output=True,
        text=True,
        timeout=30
    )
    return result


class TestCoreModelClasses:
    """Test core model class autoloading."""

    def test_tag_model_autoloads(self):
        """Verify Tag model can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Model\\Tag')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"Tag model class not found. Output: {result.stdout}"

    def test_tag_model_constants(self):
        """Verify Tag model has required status constants."""
        php_script = """
        require 'vendor/autoload.php';
        $constants = [
            'STATUS_DISABLED' => \Magento\Tag\Model\Tag::STATUS_DISABLED,
            'STATUS_PENDING' => \Magento\Tag\Model\Tag::STATUS_PENDING,
            'STATUS_APPROVED' => \Magento\Tag\Model\Tag::STATUS_APPROVED,
        ];
        echo json_encode($constants);
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"

        # Parse JSON output
        import json
        constants = json.loads(result.stdout)

        assert constants['STATUS_DISABLED'] == -1, "STATUS_DISABLED should be -1"
        assert constants['STATUS_PENDING'] == 0, "STATUS_PENDING should be 0"
        assert constants['STATUS_APPROVED'] == 1, "STATUS_APPROVED should be 1"

    def test_tag_relation_model_autoloads(self):
        """Verify Tag Relation model can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Model\\Tag\\Relation')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"Tag Relation model not found. Output: {result.stdout}"


class TestResourceModelClasses:
    """Test resource model class autoloading."""

    def test_tag_resource_model_autoloads(self):
        """Verify Tag resource model can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Model\\ResourceModel\\Tag')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"Tag resource model not found. Output: {result.stdout}"

    def test_tag_relation_resource_model_autoloads(self):
        """Verify Tag Relation resource model can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Model\\ResourceModel\\Tag\\Relation')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"Tag Relation resource model not found. Output: {result.stdout}"


class TestCollectionClasses:
    """Test collection class autoloading."""

    def test_tag_collection_autoloads(self):
        """Verify Tag Collection can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Model\\ResourceModel\\Tag\\Collection')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"Tag Collection not found. Output: {result.stdout}"

    def test_tag_relation_collection_autoloads(self):
        """Verify Tag Relation Collection can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Model\\ResourceModel\\Tag\\Relation\\Collection')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"Tag Relation Collection not found. Output: {result.stdout}"

    def test_popular_collection_autoloads(self):
        """Verify Popular Collection can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Model\\ResourceModel\\Popular\\Collection')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"Popular Collection not found. Output: {result.stdout}"

    def test_product_collection_autoloads(self):
        """Verify Product Collection can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Model\\ResourceModel\\Product\\Collection')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"Product Collection not found. Output: {result.stdout}"

    def test_customer_collection_autoloads(self):
        """Verify Customer Collection can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Model\\ResourceModel\\Customer\\Collection')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"Customer Collection not found. Output: {result.stdout}"


class TestHelperClasses:
    """Test helper class autoloading."""

    def test_helper_data_autoloads(self):
        """Verify Helper Data class can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Helper\\Data')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"Helper Data not found. Output: {result.stdout}"


class TestBlockClasses:
    """Test block class autoloading."""

    def test_list_tags_block_autoloads(self):
        """Verify ListTags block can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Block\\Product\\ListTags')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"ListTags block not found. Output: {result.stdout}"


class TestObserverClasses:
    """Test observer class autoloading."""

    def test_product_delete_observer_autoloads(self):
        """Verify ProcessProductAfterDeleteObserver can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Observer\\ProcessProductAfterDeleteObserver')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"ProcessProductAfterDeleteObserver not found. Output: {result.stdout}"

    def test_product_visibility_observer_autoloads(self):
        """Verify ProcessProductVisibilityChangeObserver can be autoloaded."""
        php_script = """
        require 'vendor/autoload.php';
        if (class_exists('Magento\\Tag\\Observer\\ProcessProductVisibilityChangeObserver')) {
            echo 'OK';
        } else {
            echo 'FAIL';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"ProcessProductVisibilityChangeObserver not found. Output: {result.stdout}"
