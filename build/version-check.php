<?php

$version_checks = [
    "$plugin_slug.php" => [
        '@Version:\s+(.*)\n@' => 'header'
    ]
];
