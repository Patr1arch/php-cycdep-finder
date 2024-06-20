#!/usr/bin/env php

<?php

use Patriarch\PhpCycdepFinder\Core\Builder\DependencyTreeBuilder;
use Patriarch\PhpCycdepFinder\Core\Finder\CyclicDependenciesFinder;
use Patriarch\PhpCycdepFinder\Core\Model\VerbosityLevel;

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

try {
    $parsedData = parseArgv($argv);
    $verbosityLevel = getVerbosityLevel($parsedData['options']);

    $dependencyTreeBuilder = (new DependencyTreeBuilder($parsedData['file_names']));
    $dependencyTree = $dependencyTreeBuilder->buildDependencyTree();
    printMessages($dependencyTreeBuilder->getMessages(), $verbosityLevel);

    $finder = new CyclicDependenciesFinder($dependencyTree);
    printMessages($finder->getMessages(), $verbosityLevel);

    return $finder->hasCyclicDependencies();
} catch (Throwable $error) {
    $errorMessages = [VerbosityLevel::LEVEL_ONE => [], VerbosityLevel::LEVEL_TWO => []];
    $errorMessages[VerbosityLevel::LEVEL_ONE][] = "Parse error: {$error->getMessage()}\n";
    $errorMessages[VerbosityLevel::LEVEL_TWO][] = print_r($error->getTrace(), true);
    printMessages($errorMessages, $verbosityLevel ?? VerbosityLevel::LEVEL_NONE);

    return 255;
}

/**
 * @param array<string> $argv files, classes, directories
 *
 * @return array{'options': array<string>, 'file_names': array<string>} array of options and array of fileNames
 */
function parseArgv(array $argv): array
{
    $fileNames = [];
    $options = [];
    unset($argv[0]);
    foreach ($argv as $arg) {
        if (is_dir($arg)) {
            $dirIterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $arg,
                    RecursiveDirectoryIterator::SKIP_DOTS
                )
            );
            foreach ($dirIterator as $file) {
                $fileNames[] = $file->getPathname();
            }
        } elseif (is_file($arg)) {
            $fileNames[] = $arg;
        } elseif (strpos($arg, '-') === 0) {
            $options[] = $arg;
        }
    }

    return ['options' => $options, 'file_names' => array_unique($fileNames)];
}

function getVerbosityLevel(array $options): int
{
    foreach ($options as $option) {
        $optionName = preg_replace('/-/', '', $option);
        if ($optionName === 'vv') {
            return VerbosityLevel::LEVEL_TWO;
        } elseif ($optionName === 'v') {
            return VerbosityLevel::LEVEL_ONE;
        }
    }

    return VerbosityLevel::LEVEL_NONE;
}


/** @param<int, array<string>> $messages */
function printMessages(array $messages, int $verbosityLevel): void
{
    switch ($verbosityLevel) {
        case VerbosityLevel::LEVEL_TWO:
            if (!empty($messages[VerbosityLevel::LEVEL_TWO])) {
                echo implode("\n", $messages[VerbosityLevel::LEVEL_TWO]) . PHP_EOL;
            }
        case VerbosityLevel::LEVEL_ONE:
            if (!empty($messages[VerbosityLevel::LEVEL_ONE])) {
                echo implode("\n", $messages[VerbosityLevel::LEVEL_ONE]) . PHP_EOL;
            }
            break;
        default:
            // Do nothing
            break;
    }
}
