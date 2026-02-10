<?php

declare(strict_types=1);

/**
 * Run Sail Pest suite repeatedly and write per-run diagnostic logs.
 *
 * Usage:
 *   php scripts/run_sail_pest_repeats.php
 */

const TOTAL_RUNS = 20;

set_time_limit(0);

$projectRoot = realpath(__DIR__.'/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$logDirectory = $projectRoot.'/storage/logs';
if (! is_dir($logDirectory) && ! mkdir($logDirectory, 0777, true) && ! is_dir($logDirectory)) {
    fwrite(STDERR, "Unable to create log directory: {$logDirectory}\n");
    exit(1);
}

$fullSuiteCommand = 'php artisan sail --args=artisan --args=test --args=--colors=never';
$focusedSuiteCommand = implode(' ', [
    'php artisan sail',
    '--args=artisan',
    '--args=test',
    '--args=tests/Feature/MultiHopOrderTest.php',
    '--args=tests/Feature/ScheduledOrderServiceTest.php',
    '--args=--testdox',
    '--args=--colors=never',
]);

for ($run = 1; $run <= TOTAL_RUNS; $run++) {
    $logFile = buildLogPath($logDirectory);
    $startedAt = new DateTimeImmutable('now');

    echo sprintf(
        "[%s] Run %d/%d -> %s\n",
        $startedAt->format(DateTimeInterface::ATOM),
        $run,
        TOTAL_RUNS,
        $logFile
    );

    $fullSuiteResult = runCommand($fullSuiteCommand, $projectRoot);
    $focusedSuiteResult = runCommand($focusedSuiteCommand, $projectRoot);
    $explicitStatuses = extractExplicitStatuses($focusedSuiteResult['output']);
    $diagnosticSnippets = extractDiagnostics(
        $fullSuiteResult['output']."\n".$focusedSuiteResult['output']
    );

    $contents = [];
    $contents[] = str_repeat('=', 100);
    $contents[] = sprintf('PEST REPEAT RUN %d OF %d', $run, TOTAL_RUNS);
    $contents[] = str_repeat('=', 100);
    $contents[] = 'Started at: '.$startedAt->format(DateTimeInterface::ATOM);
    $contents[] = 'Finished at: '.(new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);
    $contents[] = '';
    $contents[] = 'FULL SUITE COMMAND:';
    $contents[] = $fullSuiteCommand;
    $contents[] = 'Exit code: '.$fullSuiteResult['exitCode'];
    $contents[] = sprintf('Duration: %.2fs', $fullSuiteResult['durationSeconds']);
    $contents[] = '';
    $contents[] = 'FOCUSED COMMAND (EXPLICIT VALUES FOR MODIFIED TESTS):';
    $contents[] = $focusedSuiteCommand;
    $contents[] = 'Exit code: '.$focusedSuiteResult['exitCode'];
    $contents[] = sprintf('Duration: %.2fs', $focusedSuiteResult['durationSeconds']);
    $contents[] = '';
    $contents[] = 'EXPLICIT STATUS SUMMARY (MODIFIED TESTS)';
    $contents[] = str_repeat('-', 100);
    if ($explicitStatuses === []) {
        $contents[] = '[none parsed] See focused raw output below.';
    } else {
        foreach ($explicitStatuses as $statusLine) {
            $contents[] = $statusLine;
        }
    }
    $contents[] = '';
    $contents[] = 'DIAGNOSTIC SNIPPETS';
    $contents[] = str_repeat('-', 100);
    if ($diagnosticSnippets === []) {
        $contents[] = '[no known diagnostic phrases matched]';
    } else {
        foreach ($diagnosticSnippets as $snippet) {
            $contents[] = $snippet;
        }
    }
    $contents[] = '';
    $contents[] = 'FULL SUITE RAW OUTPUT';
    $contents[] = str_repeat('-', 100);
    $contents[] = $fullSuiteResult['output'];
    $contents[] = '';
    $contents[] = 'FOCUSED SUITE RAW OUTPUT';
    $contents[] = str_repeat('-', 100);
    $contents[] = $focusedSuiteResult['output'];
    $contents[] = '';

    file_put_contents($logFile, implode(PHP_EOL, $contents));
}

echo "Completed ".TOTAL_RUNS." runs. Logs written to {$logDirectory}\n";

/**
 * @return array{exitCode:int,durationSeconds:float,output:string}
 */
function runCommand(string $command, string $workingDirectory): array
{
    $started = microtime(true);
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $workingDirectory);
    if (! is_resource($process)) {
        return [
            'exitCode' => 1,
            'durationSeconds' => microtime(true) - $started,
            'output' => "Failed to start command: {$command}",
        ];
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $duration = microtime(true) - $started;

    $output = trim((string) $stdout);
    if (trim((string) $stderr) !== '') {
        $output .= ($output !== '' ? PHP_EOL.PHP_EOL : '').'[stderr]'.PHP_EOL.trim((string) $stderr);
    }

    return [
        'exitCode' => (int) $exitCode,
        'durationSeconds' => $duration,
        'output' => $output,
    ];
}

/**
 * @return list<string>
 */
function extractExplicitStatuses(string $output): array
{
    $targetNeedles = [
        'it processes multihop order scenarios with dataset',
        'it creates pending orders from due auto-submit schedules on day advance',
        'it does not auto-submit scheduled orders when funds are insufficient',
        'it creates draft orders for non-auto-submit schedules',
        'it rolls back scheduled auto-submit orders when execution fails after createOrder',
    ];

    $lines = preg_split('/\R/', $output) ?: [];
    $statuses = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        $isTargetLine = false;
        foreach ($targetNeedles as $needle) {
            if (str_contains($trimmed, $needle)) {
                $isTargetLine = true;
                break;
            }
        }

        if (! $isTargetLine) {
            continue;
        }

        $status = 'UNKNOWN';
        if (str_contains($trimmed, '✓')) {
            $status = 'PASS';
        } elseif (str_contains($trimmed, '⨯') || str_contains($trimmed, 'FAILED') || str_contains($trimmed, 'FAIL')) {
            $status = 'FAIL';
        }

        $statuses[] = sprintf('[%s] %s', $status, $trimmed);
    }

    return array_values(array_unique($statuses));
}

/**
 * @return list<string>
 */
function extractDiagnostics(string $output): array
{
    $diagnosticNeedles = [
        'Selected source location is not available for your game state.',
        'No active route is available for scheduled order.',
        'Insufficient funds',
        'Session has unexpected errors',
        'Session missing error',
        'Tests:',
        'FAILED  Tests\\Feature\\MultiHopOrderTest',
        'FAILED  Tests\\Feature\\ScheduledOrderServiceTest',
    ];

    $matches = [];
    $lines = preg_split('/\R/', $output) ?: [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        foreach ($diagnosticNeedles as $needle) {
            if (str_contains($trimmed, $needle)) {
                $matches[] = $trimmed;
                break;
            }
        }
    }

    return array_values(array_unique($matches));
}

function buildLogPath(string $logDirectory): string
{
    do {
        $timestamp = (new DateTimeImmutable('now'))->format('Ymd-His-u');
        $path = "{$logDirectory}/pest-run-{$timestamp}.log";
        if (! file_exists($path)) {
            return $path;
        }
        usleep(1000);
    } while (true);
}

