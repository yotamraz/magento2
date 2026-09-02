"""
Run all functional tests and record results.

This script runs pytest for each test file and records results using
the write_test_results.py tool.
"""
import subprocess
import sys
import os
import json
import time

# Set environment variables
os.environ['MAGENTO_ROOT'] = "C:/Users/ContainerAdministrator/.local/share/modelcode/workspace/jobs/7f89c871-191f-464d-9d72-7f78a11f1f86/workspace/magento2"
os.environ['PHP_BIN'] = "C:/Users/ContainerAdministrator/.local/share/modelcode/workspace/.pixi/envs/3508-target-app-v1/Library/bin/php.exe"
os.environ['PHP_INI'] = "C:/Users/ContainerAdministrator/.local/share/modelcode/workspace/.pixi/envs/3508-target-app-v1/Library/php.ini"

MAGENTO_ROOT = os.environ['MAGENTO_ROOT']
TEST_DIR = os.path.join(MAGENTO_ROOT, '.mcode/functional-tests/1')
WRITE_RESULTS = "C:/Users/ContainerAdministrator/.local/share/modelcode/workspace/jobs/7f89c871-191f-464d-9d72-7f78a11f1f86/.claude/skills/sandbox-app-runner/write_test_results.py"

# Test files to run
TEST_FILES = [
    'test_module_registration.py',
    'test_xml_schemas.py',
    'test_class_autoloading.py',
    'test_helper_methods.py',
    'test_api_interfaces.py',
]


def run_pytest_file(test_file):
    """Run pytest on a single file and return results."""
    print(f"\n{'='*60}")
    print(f"Running {test_file}...")
    print(f"{'='*60}\n")

    start_time = time.time()
    result = subprocess.run(
        [sys.executable, '-m', 'pytest', test_file, '-v', '--tb=short'],
        cwd=TEST_DIR,
        capture_output=True,
        text=True,
        timeout=300
    )
    duration_ms = (time.time() - start_time) * 1000

    print(result.stdout)
    if result.stderr:
        print("STDERR:", result.stderr, file=sys.stderr)

    return {
        'returncode': result.returncode,
        'stdout': result.stdout,
        'stderr': result.stderr,
        'duration_ms': duration_ms
    }


def record_test_result(test_name, passed, duration_ms, output, error_msg=None):
    """Record a test result using write_test_results.py."""
    cmd = [
        sys.executable,
        WRITE_RESULTS,
        '--name', test_name,
        '--repo-type', 'target',
        '--repo-name', 'magento2',
        '--category', 'HAPPY_PATH',
        '--command', 'pytest',
        '--args', test_name,
        '--expected-exit-code', '0',
        '--actual-exit-code', '0' if passed else '1',
        '--actual-output', output[:4000],  # Truncate to 4KB
        '--duration-ms', str(duration_ms),
        '--description', f"Functional test: {test_name}",
        '--test-run-index', '1'  # Increment to start fresh
    ]

    if not passed and error_msg:
        cmd.extend(['--comparison-note', error_msg[:500]])

    result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    if result.returncode != 0:
        print(f"WARNING: Failed to record result for {test_name}: {result.stderr}")
    else:
        print(f"✓ Recorded result for {test_name}: {'PASSED' if passed else 'FAILED'}")


def main():
    """Run all tests and record results."""
    print("Starting functional tests for Magento_Tag module...")
    print(f"Test directory: {TEST_DIR}")
    print(f"Environment: MAGENTO_ROOT={MAGENTO_ROOT}")

    overall_passed = 0
    overall_failed = 0

    for test_file in TEST_FILES:
        result = run_pytest_file(test_file)

        # Determine if test passed (pytest returns 0 for all pass)
        passed = result['returncode'] == 0

        if passed:
            overall_passed += 1
        else:
            overall_failed += 1

        # Record the result
        test_name = test_file.replace('.py', '')
        error_msg = result['stderr'] if not passed else None
        record_test_result(test_name, passed, result['duration_ms'], result['stdout'], error_msg)

    print(f"\n{'='*60}")
    print(f"SUMMARY: {overall_passed} passed, {overall_failed} failed")
    print(f"{'='*60}\n")

    sys.exit(0 if overall_failed == 0 else 1)


if __name__ == '__main__':
    main()
