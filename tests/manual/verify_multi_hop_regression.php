<?php

echo "Starting Multi-Hop Order Regression Verification...\n";
echo "=================================================\n\n";

$command = './vendor/bin/pest tests/Feature/MultiHopOrderTest.php';

echo "Executing: $command\n\n";

$output = [];
$returnVar = 0;
exec($command, $output, $returnVar);

foreach ($output as $line) {
    echo $line . "\n";
}

echo "\n=================================================\n";

if ($returnVar === 0) {
    echo "Verification PASSED: All scenarios handled correctly.\n";
    exit(0);
} else {
    echo "Verification FAILED: Some tests did not pass.\n";
    exit(1);
}
