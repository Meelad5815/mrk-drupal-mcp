<?php
declare(strict_types=1);

/**
 * MRK Drupal MCP - stateless Streamable HTTP JSON-RPC endpoint.
 *
 * Copy config.example.php to config.local.php and fill in secrets on the
 * hosting account. Never commit config.local.php.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configFile = __DIR__ . '/config.local.php';
if (!is_file($configFile)) {
    http_response_code(503);
    echo json_encode(['error' => 'MRK MCP is not configured. Add config.local.php.']);
    exit;
}
$config = require $configFile;

function jsonResponse(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function rpcError($id, int $code, string $message): never {
    jsonResponse([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => ['code' => $code, 'message' => $message],
    ]);
}

function requireAuth(array $config): void {
    $expected = (string)($config['mcp_bearer_token'] ?? '');
    if ($expected === '') {
        jsonResponse(['error' => 'MCP authentication is not configured.'], 503);
    }
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    $provided = preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) ? $m[1] : '';
    if ($provided === '' || !hash_equals($expected, $provided)) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
}

function drupalRequest(array $config, string $path): array {
    $base = rtrim((string)($config['drupal_base_url'] ?? ''), '/');
    if ($base === '') {
        throw new RuntimeException('Drupal base URL is not configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required on the hosting account.');
    }
    $url = $base . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    $headers = ['Accept: application/vnd.api+json, application/json'];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    $user = (string)($config['drupal_basic_auth_username'] ?? '');
    $pass = (string)($config['drupal_basic_auth_password'] ?? '');
    if ($user !== '' && $pass !== '') {
        $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        $options[CURLOPT_USERPWD] = $user . ':' . $pass;
    }
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('Drupal request failed: ' . $error);
    }
    $decoded = json_decode($body, true);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Drupal returned HTTP ' . $status . '. Check JSON:API and account permissions.');
    }
    if (!is_array($decoded)) {
        throw new RuntimeException('Drupal returned an invalid JSON response.');
    }
    return $decoded;
}

function toolDefinitions(): array {
    return [
        [
            'name' => 'drupal_site_info',
            'description' => 'Read basic information about the connected Drupal site. Read-only.',
            'inputSchema' => ['type' => 'object', 'properties' => [], 'additionalProperties' => false],
            'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false],
        ],
        [
            'name' => 'drupal_list_content',
            'description' => 'List published Drupal content through JSON:API. Read-only. Allowed bundles are configured by the site administrator.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'bundle' => ['type' => 'string', 'description' => 'Drupal content type machine name, for example page or article.'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
                ],
                'required' => ['bundle'],
                'additionalProperties' => false,
            ],
            'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false],
        ],
    ];
}

function callTool(array $config, string $name, array $args): array {
    if ($name === 'drupal_site_info') {
        $base = rtrim((string)($config['drupal_base_url'] ?? ''), '/');
        return [
            'site_url' => $base,
            'integration' => 'Drupal JSON:API',
            'status' => 'MCP endpoint authenticated; Drupal API connectivity is configured separately.',
            'write_access' => false,
        ];
    }
    if ($name === 'drupal_list_content') {
        $bundle = (string)($args['bundle'] ?? '');
        $allowed = $config['allowed_content_bundles'] ?? ['page', 'article'];
        if (!in_array($bundle, $allowed, true)) {
            throw new InvalidArgumentException('This content type is not in the allowed_content_bundles list.');
        }
        $limit = max(1, min(25, (int)($args['limit'] ?? 10)));
        $path = 'jsonapi/node/' . rawurlencode($bundle) . '?page[limit]=' . $limit . '&sort=-created';
        $data = drupalRequest($config, $path);
        $items = [];
        foreach (($data['data'] ?? []) as $item) {
            $attrs = $item['attributes'] ?? [];
            $items[] = [
                'id' => $item['id'] ?? null,
                'type' => $item['type'] ?? null,
                'title' => $attrs['title'] ?? '',
                'status' => $attrs['status'] ?? null,
                'created' => $attrs['created'] ?? null,
                'changed' => $attrs['changed'] ?? null,
            ];
        }
        return ['bundle' => $bundle, 'count' => count($items), 'items' => $items];
    }
    throw new InvalidArgumentException('Unknown tool: ' . $name);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Allow: POST, OPTIONS');
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST, OPTIONS');
    jsonResponse(['error' => 'Use POST with a JSON-RPC 2.0 MCP request.'], 405);
}

requireAuth($config);
$raw = file_get_contents('php://input');
$request = json_decode($raw ?: '', true);
if (!is_array($request) || ($request['jsonrpc'] ?? '') !== '2.0' || !array_key_exists('method', $request)) {
    rpcError($request['id'] ?? null, -32600, 'Invalid JSON-RPC request.');
}
$id = $request['id'] ?? null;
$method = (string)$request['method'];
$params = is_array($request['params'] ?? null) ? $request['params'] : [];

try {
    if ($method === 'initialize') {
        $version = (string)($params['protocolVersion'] ?? '2025-03-26');
        jsonResponse([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => $version,
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'mrk-drupal-mcp', 'version' => '0.1.0'],
                'instructions' => 'Read-only Drupal MCP starter. Only use listed tools; no filesystem or content writes are enabled yet.',
            ],
        ]);
    }
    if ($method === 'notifications/initialized') {
        http_response_code(202);
        exit;
    }
    if ($method === 'ping') {
        jsonResponse(['jsonrpc' => '2.0', 'id' => $id, 'result' => (object)[]]);
    }
    if ($method === 'tools/list') {
        jsonResponse(['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => toolDefinitions()]]);
    }
    if ($method === 'tools/call') {
        $toolName = (string)($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        try {
            $result = callTool($config, $toolName, $arguments);
            jsonResponse([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => ['content' => [['type' => 'text', 'text' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]], 'isError' => false],
            ]);
        } catch (Throwable $e) {
            jsonResponse([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true],
            ]);
        }
    }
    rpcError($id, -32601, 'Method not found: ' . $method);
} catch (Throwable $e) {
    rpcError($id, -32603, 'Internal server error.');
}
