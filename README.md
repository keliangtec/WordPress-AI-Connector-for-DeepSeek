[简体中文](README.zh-CN.md)

# DeepSeek AI Provider for WordPress

DeepSeek AI Provider for WordPress registers DeepSeek as a provider for the WordPress AI Client.

## Capabilities

- Discovers available models dynamically with `GET /v1/models`.
- Supports text generation, chat history, function calling, and DeepSeek reasoning-history replay through the WordPress AI Client.
- Advertises text and image input only for the exact, case-sensitive model ID `deepseek-flash`. Every other current model and any unknown future model default to text-only input.
- Passes image inputs represented as remote URLs or `data:` URIs through the WordPress PHP AI Client to DeepSeek's OpenAI-compatible Chat Completions endpoint.
- Uses the WordPress AI Client connector to manage the DeepSeek API key.

The plugin does not currently provide a WordPress Media Library UI, an upload workflow, Files API integration, image `detail` controls, or DeepSeek Responses API support. Generation requests are currently fixed to the OpenAI-compatible Chat Completions endpoint. The plugin does not upload images independently of the WordPress PHP AI Client.

## Requirements

- WordPress 7.0 or later, with the WordPress AI Client available.
- PHP 7.4 or later. The production target environment is PHP 8.4.
- A DeepSeek API key configured in the WordPress AI Client connector.

## Installation

1. Place the plugin directory in `wp-content/plugins/`.
2. Activate **DeepSeek AI Provider for WordPress**.
3. Configure the DeepSeek connector through the WordPress AI Client.

## Image input

The OpenAI-compatible parent class in `wordpress/php-ai-client` serializes `File` image inputs into `image_url` content blocks. This plugin is responsible for declaring which DeepSeek models support image input and for applying DeepSeek-specific endpoint and request differences; it does not encode the images itself. Image transport is available only when the AI Client selects a model whose advertised input modalities include images, which this plugin currently declares only for the exact model ID `deepseek-flash`.

## Development

Install development dependencies with Composer, then run:

```bash
composer validate --strict
composer test
composer phpcs
composer phpstan
```

CI covers the minimum supported PHP version, 7.4, and the production target, PHP 8.4.

## External service

The plugin sends model-list requests and AI prompts to the DeepSeek API at `https://api.deepseek.com/v1`. Model discovery uses `GET /v1/models`; generation uses the OpenAI-compatible Chat Completions endpoint. Requests include the API key managed by the WordPress AI Client connector and the prompt content supplied for processing, including image URLs or data URIs when present. DeepSeek's terms and privacy policy govern data handled by that service.

## Credits and license

This project is based on the GPL-licensed **AI Provider For DeepSeek 1.0.3**, originally created by **Sajjad Hossain Sagor**. The original author's attribution is retained with thanks. The original project and this fork are distributed under **GPL-2.0-or-later**. See `NOTICE.md` and `license.txt` for details.
