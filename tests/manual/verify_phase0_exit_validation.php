<?php
/**
 * Manual Test Runner: Phase 0 Exit Validation Matrix
 * Track: conductor/tracks/arch_remediation_20260207/plan.md
 *
 * Executes Phase 0 manual verification scripts and static guards:
 * - Monetary invariants script
 * - User isolation script
 * - Source guard checks for legacy cash initialization regressions
 */

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (app()->environment('production')) {
    fwrite(STDERR, "Error: Cannot run manual tests in production.\n");
    exit(1);
}

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

$testRunId = 'phase0_exit_' . Carbon::now()->format('Y_m_d_His');
$logFile = storage_path("logs/manual_tests/{$testRunId}.log");

if (! is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

config(['logging.channels.manual_test' => [
    'driver' => 'single',
    'path' => $logFile,
    'level' => 'debug',
]]);

function logInfo(string $message, array $context = []): void
{
    Log::channel('manual_test')->info($message, $context);
    echo "[INFO] {$message}\n";
}

function logError(string $message, array $context = []): void
{
    Log::channel('manual_test')->error($message, $context);
    echo "[ERROR] {$message}\n";
}

function runPhpScript(string $scriptPath): int
{
    $php = escapeshellarg(PHP_BINARY);
    $script = escapeshellarg($scriptPath);
    $command = "{$php} {$script} 2>&1";

    logInfo("Executing {$scriptPath}");
    exec($command, $output, $exitCode);

    foreach ($output as $line) {
        echo "    {$line}\n";
    }

    logInfo("Script completed", ['script' => $scriptPath, 'exit_code' => $exitCode]);

    return $exitCode;
}

function staticGuardCheck(): array
{
    $violations = [];
    $required = [
        app_path('Actions/InitializeNewGame.php'),
        app_path('Http/Middleware/HandleInertiaRequests.php'),
        app_path('Http/Controllers/GameController.php'),
    ];

    foreach ($required as $path) {
        $contents = file_get_contents($path);
        if ($contents === false) {
            $violations[] = "Unable to read {$path}";
            continue;
        }

        if (strpos($contents, '10000.00') !== false) {
            $violations[] = "Legacy 10000.00 initializer found in {$path}";
        }

        if (strpos($contents, '1000000') === false) {
            $violations[] = "Expected 1000000 invariant marker missing in {$path}";
        }
    }

    return $violations;
}

$failureCount = 0;

logInfo("=== Starting Phase 0 Exit Validation: {$testRunId} ===");

$violations = staticGuardCheck();
if (count($violations) > 0) {
    $failureCount += count($violations);
    foreach ($violations as $violation) {
        logError($violation);
    }
} else {
    logInfo('[PASS] static guard checks for initialization invariants');
}

$scripts = [
    base_path('tests/manual/verify_phase0_monetary_canonicalization.php'),
    base_path('tests/manual/verify_phase0_user_isolation.php'),
];

foreach ($scripts as $script) {
    $exitCode = runPhpScript($script);
    if ($exitCode !== 0) {
        $failureCount++;
        logError('[FAIL] child verification script failed', ['script' => $script, 'exit_code' => $exitCode]);
    } else {
        logInfo('[PASS] child verification script succeeded', ['script' => $script]);
    }
}

logInfo("=== Finished Phase 0 Exit Validation: {$testRunId} ===", [
    'failures' => $failureCount,
]);
echo "\nSummary: ".($failureCount === 0 ? 'PASS' : 'FAIL')." ({$failureCount} failure(s))\n";
echo "Log: {$logFile}\n";

exit($failureCount > 0 ? 1 : 0);
