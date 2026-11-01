<?php
/**
 * Copy to config.local.php on the hosting account.
 * Replace all placeholder values. Never commit config.local.php.
 */
return [
    // Generate a long random value (at least 32 bytes) for the MCP bearer token.
    'mcp_bearer_token' => 'REPLACE_WITH_A_LONG_RANDOM_SECRET',

    // Drupal site base URL, without a trailing slash.
    'drupal_base_url' => 'https://muhammadrajab786.free.nf',

    // Optional: only use if Drupal's Basic Auth module is enabled and the
    // integration account has minimum required permissions.
    'drupal_basic_auth_username' => '',
    'drupal_basic_auth_password' => '',

    // Explicit allow-list: only content types listed here can be queried.
    'allowed_content_bundles' => ['page', 'article'],
];
