# MRK Drupal MCP

A secure, PHP-based Model Context Protocol (MCP) bridge for a Drupal website, designed for shared PHP hosting without Vercel.

## Status
Initial project scaffold. No live MCP endpoint is deployed yet.

## Architecture
- GitHub is the source-code repository and version history.
- The MCP endpoint is intended to run as a stateless PHP HTTPS endpoint on the existing hosting account, subject to host support for outbound HTTPS requests and PHP execution.
- Drupal integration uses Drupal's authenticated JSON:API and a small custom module for privileged development operations.
- Gemini must support connecting to a remote MCP server in the user's account/region before this endpoint can be added there.

## Security principles
- Never commit API keys, Drupal passwords, FTP credentials, or access tokens.
- Use a dedicated Drupal integration account with least-privilege permissions.
- Require explicit confirmation for destructive or production-changing operations.
- Keep file editing restricted to approved Drupal project directories; never expose arbitrary filesystem access.
- Back up files and configuration before writes.

## Planned phases
1. Verify PHP version, HTTPS, outbound network access, and hosting restrictions.
2. Implement stateless MCP JSON-RPC endpoint in PHP.
3. Add Drupal read-only tools (site info, content listing, content read).
4. Add guarded content editing tools.
5. Add optional development tools via a Drupal custom module and a separately secured deployment workflow.
6. Test the endpoint and document Gemini connection steps.

## Important limitation
GitHub stores and versions code; it does not execute or host an MCP endpoint. A live HTTPS PHP endpoint must run on a supported hosting service. This project deliberately does not use Vercel.
