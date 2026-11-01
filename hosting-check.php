<?php
declare(strict_types=1);

/**
 * Temporary, non-secret hosting diagnostics for MRK Drupal MCP.
 * Delete this file after checking the hosting environment.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'php_version' => PHP_VERSION,
    'php_version_ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'curl_extension' => extension_loaded('curl'),
    'openssl_extension' => extension_loaded('openssl'),
    'https_scheme' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'next_step' => 'Delete this file after checking. Do not share hosting passwords or API keys.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
