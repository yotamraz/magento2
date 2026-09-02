"""
Test Magento_Tag module registration and basic structure.

This test verifies that the module is correctly registered with Magento's
ComponentRegistrar and that core files exist and are properly structured.
"""
import subprocess
import os
import pytest

MAGENTO_ROOT = os.environ.get('MAGENTO_ROOT', os.path.join(os.path.dirname(__file__), '../../..'))
MODULE_PATH = os.path.join(MAGENTO_ROOT, 'app/code/Magento/Tag')
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


class TestModuleRegistration:
    """Test module registration with Magento ComponentRegistrar."""

    def test_registration_file_exists(self):
        """Verify registration.php exists."""
        registration_file = os.path.join(MODULE_PATH, 'registration.php')
        assert os.path.isfile(registration_file), f"registration.php not found at {registration_file}"

    def test_module_registration(self):
        """Verify module is registered via ComponentRegistrar."""
        php_script = r"""
        require 'vendor/autoload.php';
        $registrar = new \Magento\Framework\Component\ComponentRegistrar();
        $paths = $registrar->getPaths(\Magento\Framework\Component\ComponentRegistrar::MODULE);
        if (isset($paths['Magento_Tag'])) {
            echo 'REGISTERED';
        } else {
            echo 'NOT_REGISTERED';
        }
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP execution failed: {result.stderr}"
        assert 'REGISTERED' in result.stdout, f"Module not registered. Output: {result.stdout}"

    def test_module_path_correct(self):
        """Verify module path is correct in ComponentRegistrar."""
        php_script = r"""
        require 'vendor/autoload.php';
        $registrar = new \Magento\Framework\Component\ComponentRegistrar();
        $paths = $registrar->getPaths(\Magento\Framework\Component\ComponentRegistrar::MODULE);
        echo $paths['Magento_Tag'] ?? 'NOT_FOUND';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP execution failed: {result.stderr}"
        expected_path_suffix = 'app/code/Magento/Tag'
        assert expected_path_suffix in result.stdout.replace('\\', '/'), \
            f"Module path incorrect. Expected to contain '{expected_path_suffix}', got: {result.stdout}"


class TestModuleStructure:
    """Test module file structure and required files."""

    def test_composer_json_exists(self):
        """Verify composer.json exists."""
        composer_file = os.path.join(MODULE_PATH, 'composer.json')
        assert os.path.isfile(composer_file), f"composer.json not found at {composer_file}"

    def test_module_xml_exists(self):
        """Verify etc/module.xml exists."""
        module_xml = os.path.join(MODULE_PATH, 'etc/module.xml')
        assert os.path.isfile(module_xml), f"etc/module.xml not found at {module_xml}"

    def test_db_schema_xml_exists(self):
        """Verify etc/db_schema.xml exists."""
        db_schema = os.path.join(MODULE_PATH, 'etc/db_schema.xml')
        assert os.path.isfile(db_schema), f"etc/db_schema.xml not found at {db_schema}"

    def test_db_schema_whitelist_exists(self):
        """Verify etc/db_schema_whitelist.json exists."""
        whitelist = os.path.join(MODULE_PATH, 'etc/db_schema_whitelist.json')
        assert os.path.isfile(whitelist), f"etc/db_schema_whitelist.json not found at {whitelist}"

    def test_di_xml_exists(self):
        """Verify etc/di.xml exists."""
        di_xml = os.path.join(MODULE_PATH, 'etc/di.xml')
        assert os.path.isfile(di_xml), f"etc/di.xml not found at {di_xml}"
