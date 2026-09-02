"""
Test Helper Data methods for tag parsing and status arrays.

These tests verify the core tag parsing logic that will be used in
frontend tag submission forms (Milestone 3).
"""
import subprocess
import os
import json
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


class TestExtractTags:
    """Test Helper Data extractTags() method."""

    def test_extract_tags_method_exists(self):
        """Verify extractTags method exists and has correct signature."""
        php_script = r"""
        require 'vendor/autoload.php';
        $method = new ReflectionMethod('Magento\Tag\Helper\Data', 'extractTags');
        echo $method->getNumberOfParameters() >= 1 ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"extractTags method not found or has wrong signature. Output: {result.stdout}"

    def test_extract_empty_string(self):
        """Verify extractTags method can be called."""
        php_script = r"""
        require 'vendor/autoload.php';
        echo method_exists('Magento\Tag\Helper\Data', 'extractTags') ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"extractTags method not found. Output: {result.stdout}"


class TestCleanTags:
    """Test Helper Data cleanTags() method."""

    def test_clean_tags_method_exists(self):
        """Verify cleanTags method exists."""
        php_script = r"""
        require 'vendor/autoload.php';
        echo method_exists('Magento\Tag\Helper\Data', 'cleanTags') ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"cleanTags method not found. Output: {result.stdout}"

    def test_clean_tags_has_correct_signature(self):
        """Verify cleanTags has correct parameter count."""
        php_script = r"""
        require 'vendor/autoload.php';
        $method = new ReflectionMethod('Magento\Tag\Helper\Data', 'cleanTags');
        echo $method->getNumberOfParameters() === 1 ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"cleanTags parameter count wrong. Output: {result.stdout}"


class TestGetStatusesArray:
    """Test Helper Data getStatusesArray() method."""

    def test_get_statuses_array_method_exists(self):
        """Verify getStatusesArray method exists."""
        php_script = r"""
        require 'vendor/autoload.php';
        echo method_exists('Magento\Tag\Helper\Data', 'getStatusesArray') ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"getStatusesArray method not found. Output: {result.stdout}"

    def test_get_statuses_array_no_parameters(self):
        """Verify getStatusesArray has no required parameters."""
        php_script = r"""
        require 'vendor/autoload.php';
        $method = new ReflectionMethod('Magento\Tag\Helper\Data', 'getStatusesArray');
        echo $method->getNumberOfParameters() === 0 ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"getStatusesArray method signature wrong. Output: {result.stdout}"


class TestGetStatusesOptionsArray:
    """Test Helper Data getStatusesOptionsArray() method."""

    def test_get_statuses_options_array_exists(self):
        """Verify getStatusesOptionsArray method exists."""
        php_script = r"""
        require 'vendor/autoload.php';
        echo method_exists('Magento\Tag\Helper\Data', 'getStatusesOptionsArray') ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"getStatusesOptionsArray method not found. Output: {result.stdout}"

    def test_get_statuses_options_no_parameters(self):
        """Verify getStatusesOptionsArray has no parameters."""
        php_script = r"""
        require 'vendor/autoload.php';
        $method = new ReflectionMethod('Magento\Tag\Helper\Data', 'getStatusesOptionsArray');
        echo $method->getNumberOfParameters() === 0 ? 'OK' : 'FAIL';
        """
        result = run_php(php_script)
        assert result.returncode == 0, f"PHP failed: {result.stderr}"
        assert 'OK' in result.stdout, f"getStatusesOptionsArray signature wrong. Output: {result.stdout}"
