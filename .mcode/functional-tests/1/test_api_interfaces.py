"""
Test API interface definitions for service contracts.

Verifies that the TagRepositoryInterface and TagRelationRepositoryInterface
are properly defined with the expected CRUD methods.
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


class TestTagInterface:
    """Test TagInterface data contract."""

    def test_tag_interface_exists(self):
        """Verify TagInterface is defined."""
        php_script = """
        require 'vendor/autoload.php';
        echo interface_exists('Magento\\Tag\\Api\\Data\\TagInterface') ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagInterface not found. Output: {result.stdout}"

    def test_tag_interface_has_getter_methods(self):
        """Verify TagInterface has required getter methods."""
        php_script = """
        require 'vendor/autoload.php';
        $methods = get_class_methods('Magento\\Tag\\Api\\Data\\TagInterface');
        $required = ['getTagId', 'getName', 'getStatus'];
        $has_all = true;
        foreach ($required as $method) {
            if (!in_array($method, $methods)) {
                $has_all = false;
                break;
            }
        }
        echo $has_all ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagInterface missing required getters. Output: {result.stdout}"

    def test_tag_interface_has_setter_methods(self):
        """Verify TagInterface has required setter methods."""
        php_script = """
        require 'vendor/autoload.php';
        $methods = get_class_methods('Magento\\Tag\\Api\\Data\\TagInterface');
        $required = ['setName', 'setStatus'];
        $has_all = true;
        foreach ($required as $method) {
            if (!in_array($method, $methods)) {
                $has_all = false;
                break;
            }
        }
        echo $has_all ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagInterface missing required setters. Output: {result.stdout}"


class TestTagRelationInterface:
    """Test TagRelationInterface data contract."""

    def test_tag_relation_interface_exists(self):
        """Verify TagRelationInterface is defined."""
        php_script = """
        require 'vendor/autoload.php';
        echo interface_exists('Magento\\Tag\\Api\\Data\\TagRelationInterface') ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagRelationInterface not found. Output: {result.stdout}"

    def test_tag_relation_interface_has_getters(self):
        """Verify TagRelationInterface has required getter methods."""
        php_script = """
        require 'vendor/autoload.php';
        $methods = get_class_methods('Magento\\Tag\\Api\\Data\\TagRelationInterface');
        $required = ['getTagId', 'getProductId', 'getCustomerId', 'getStoreId'];
        $has_all = true;
        foreach ($required as $method) {
            if (!in_array($method, $methods)) {
                $has_all = false;
                break;
            }
        }
        echo $has_all ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagRelationInterface missing required getters. Output: {result.stdout}"


class TestTagRepositoryInterface:
    """Test TagRepositoryInterface service contract."""

    def test_tag_repository_interface_exists(self):
        """Verify TagRepositoryInterface is defined."""
        php_script = """
        require 'vendor/autoload.php';
        echo interface_exists('Magento\\Tag\\Api\\TagRepositoryInterface') ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagRepositoryInterface not found. Output: {result.stdout}"

    def test_tag_repository_has_save_method(self):
        """Verify TagRepositoryInterface has save method."""
        php_script = """
        require 'vendor/autoload.php';
        $methods = get_class_methods('Magento\\Tag\\Api\\TagRepositoryInterface');
        echo in_array('save', $methods) ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagRepositoryInterface missing save method. Output: {result.stdout}"

    def test_tag_repository_has_get_method(self):
        """Verify TagRepositoryInterface has get method."""
        php_script = """
        require 'vendor/autoload.php';
        $methods = get_class_methods('Magento\\Tag\\Api\\TagRepositoryInterface');
        echo in_array('get', $methods) ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagRepositoryInterface missing get method. Output: {result.stdout}"

    def test_tag_repository_has_delete_method(self):
        """Verify TagRepositoryInterface has delete method."""
        php_script = """
        require 'vendor/autoload.php';
        $methods = get_class_methods('Magento\\Tag\\Api\\TagRepositoryInterface');
        echo in_array('delete', $methods) ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagRepositoryInterface missing delete method. Output: {result.stdout}"

    def test_tag_repository_has_get_list_method(self):
        """Verify TagRepositoryInterface has getList method."""
        php_script = """
        require 'vendor/autoload.php';
        $methods = get_class_methods('Magento\\Tag\\Api\\TagRepositoryInterface');
        echo in_array('getList', $methods) ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagRepositoryInterface missing getList method. Output: {result.stdout}"


class TestTagRelationRepositoryInterface:
    """Test TagRelationRepositoryInterface service contract."""

    def test_tag_relation_repository_interface_exists(self):
        """Verify TagRelationRepositoryInterface is defined."""
        php_script = """
        require 'vendor/autoload.php';
        echo interface_exists('Magento\\Tag\\Api\\TagRelationRepositoryInterface') ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagRelationRepositoryInterface not found. Output: {result.stdout}"

    def test_tag_relation_repository_has_crud_methods(self):
        """Verify TagRelationRepositoryInterface has save and get methods."""
        php_script = """
        require 'vendor/autoload.php';
        $methods = get_class_methods('Magento\\Tag\\Api\\TagRelationRepositoryInterface');
        $required = ['save', 'get'];
        $has_all = true;
        foreach ($required as $method) {
            if (!in_array($method, $methods)) {
                $has_all = false;
                break;
            }
        }
        echo $has_all ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagRelationRepositoryInterface missing CRUD methods. Output: {result.stdout}"


class TestTagSearchResultsInterface:
    """Test TagSearchResultsInterface for repository getList results."""

    def test_search_results_interface_exists(self):
        """Verify TagSearchResultsInterface is defined."""
        php_script = """
        require 'vendor/autoload.php';
        echo interface_exists('Magento\\Tag\\Api\\Data\\TagSearchResultsInterface') ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagSearchResultsInterface not found. Output: {result.stdout}"

    def test_search_results_has_items_method(self):
        """Verify TagSearchResultsInterface has getItems method."""
        php_script = """
        require 'vendor/autoload.php';
        $methods = get_class_methods('Magento\\Tag\\Api\\Data\\TagSearchResultsInterface');
        echo in_array('getItems', $methods) ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"TagSearchResultsInterface missing getItems. Output: {result.stdout}"
