<?php

use Symfony\Component\Yaml\Yaml;

if (!isset($argv[1])) {
    echo 'No argument with version provided', PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

$yaml = Yaml::parseFile(__DIR__ . '/../src/Resources/config/services.yaml', Yaml::PARSE_CUSTOM_TAGS);
if ($yaml['parameters']['unleash.bundle.version'] !== $argv[1]) {
    echo sprintf(
        "The version provided is '%s', the SDK is set to version '%s'",
        $argv[1],
        $yaml['parameters']['unleash.bundle.version'],
    ), PHP_EOL;
    exit(2);
}
