"""
Pytest configuration for Magento_Tag functional tests.

Sets up environment variables and common fixtures.
"""
import os
import pytest


@pytest.fixture(scope="session", autouse=True)
def setup_environment():
    """Set up environment variables from the lifecycle setup."""
    # These environment variables should be set by the lifecycle runner
    # but we provide defaults for direct pytest execution
    if 'MAGENTO_ROOT' not in os.environ:
        os.environ['MAGENTO_ROOT'] = os.path.join(os.path.dirname(__file__), '../../..')

    if 'PHP_BIN' not in os.environ:
        os.environ['PHP_BIN'] = 'php'

    # PHP_INI may be empty - that's fine
    if 'PHP_INI' not in os.environ:
        os.environ['PHP_INI'] = ''

    yield

    # No teardown needed
