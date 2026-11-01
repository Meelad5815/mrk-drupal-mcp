# Deployment to the existing Free.nf hosting

This project does not use Vercel. GitHub stores the source; the PHP endpoint must be uploaded to and executed by the existing hosting account.

## Before uploading

1. Confirm the hosting account supports PHP 8.1 or newer.
2. Confirm the PHP cURL extension is enabled.
3. Confirm the website has valid HTTPS.
4. Confirm outbound HTTPS requests to the Drupal JSON:API endpoint are permitted.
5. In Drupal, enable JSON:API and (if using Basic Auth) the Basic Authentication module. Grant the integration account only the permissions needed for read-only content access.

## Upload

1. Download the repository ZIP from GitHub.
2. Upload `mcp.php` and `config.example.php` to a private folder under the website's document root, or preferably place the configuration file outside the public document root.
3. Create `config.local.php` from `config.example.php`; set a long random `mcp_bearer_token`.
4. Set the Drupal base URL and optional Drupal API credentials.
5. Ensure direct web access to `config.local.php` is blocked. Do not upload secrets to GitHub.
6. The initial endpoint URL will be `https://YOUR-DOMAIN/mcp.php` if uploaded to the document root.

## Current tool scope

The first scaffold exposes only:
- `drupal_site_info`
- `drupal_list_content`

Both are read-only. It does not yet edit content, themes, modules, or files.

## Gemini availability

Gemini custom MCP apps currently require a supported account and region. Google currently documents US availability for personal accounts, age 18+, and Keep Activity enabled. Verify that Connected Apps / Developer Mode is visible in the user's Gemini account before expecting to connect the endpoint.

## Security

Do not expose unrestricted filesystem access, FTP credentials, database credentials, or arbitrary PHP execution to an AI tool. Add write operations only after authentication, role checks, backups, allow-listed paths, and explicit confirmation are implemented.
