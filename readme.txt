=== AI Connector for DeepSeek Guducat.ver ===
Contributors: sajjad67
Tags: ai, deepseek, artificial-intelligence, connector
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 3.1.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Registers DeepSeek as a provider for the WordPress AI Client and provides administrator model management tools.

== Description ==

AI Connector for DeepSeek Guducat.ver registers DeepSeek with the WordPress AI Client included in WordPress 7.x.

Features:

* Dynamic model discovery with `GET /v1/models`.
* OpenAI-compatible text generation, chat history, function calling, and DeepSeek reasoning-history replay.
* Text and image input are advertised only for the exact, case-sensitive model ID `deepseek-flash`; every other current or unknown model defaults to text-only input.
* Remote URL and `data:` URI image inputs are passed through the WordPress PHP AI Client to the OpenAI-compatible Chat Completions endpoint.
* API key management through the WordPress AI Client connector system.
* Administrator-only connector page under Settings > DeepSeek Connector with click-to-query balance and model capability management.
* One configurable experimental model can be added as a local fallback, with administrator-selected text or text + image input.
* Read-only detection and guidance for the WordPress AI Request Logging experiment.
* DeepSeek request-log enrichment with cache usage, reasoning tokens, and estimated costs using versioned built-in or administrator-defined pricing rules.

This plugin does not currently provide a Media Library UI, an upload workflow, Files API integration, image `detail` controls, or DeepSeek Responses API support. Generation requests are currently fixed to the OpenAI-compatible Chat Completions endpoint. The plugin does not upload images independently of the WordPress PHP AI Client.

Requirements:

* WordPress 7.0 or later, with the WordPress AI Client available.
* PHP 7.4 or later. The production target environment is PHP 8.4.
* A DeepSeek API key configured in the WordPress AI Client connector.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Activate AI Connector for DeepSeek Guducat.ver.
3. Configure DeepSeek through the WordPress AI Client connector.

Enable AI Request Logging in the WordPress AI settings to record requests. Logs remain on the existing Tools > AI Request Logs screen; this connector does not create a separate request-history table. Cost values are estimates stored with the pricing snapshot used for each request and may differ from the provider invoice.

== Frequently Asked Questions ==

= How do I get a DeepSeek API key? =

Create an API key through the DeepSeek Platform.

== External Services ==

This plugin connects to the DeepSeek API at `https://api.deepseek.com/v1` to discover models and process AI prompts. When an administrator explicitly refreshes the balance on the DeepSeek Connector page, it also sends an authenticated request to `https://api.deepseek.com/user/balance`. The API key managed by WordPress and prompt content supplied for processing are sent to DeepSeek when these features are used.

Model discovery uses `GET /v1/models`. Generation requests use the OpenAI-compatible Chat Completions endpoint. Balance requests use `GET /user/balance` and are limited to administrators with `manage_options`, a valid WordPress nonce, and an explicit refresh submission on the DeepSeek Connector page. When the WordPress PHP AI Client supplies image inputs as remote URLs or `data:` URIs, those values are included in the request. The plugin does not provide a separate image uploader, Media Library UI, Files API integration, or image `detail` control. The API key, `Authorization` header, and raw provider error details are handled server-side and are not rendered in the browser.

Service provider: DeepSeek

* Website: https://www.deepseek.com/
* Terms: https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html
* Privacy policy: https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html

== Credits ==

Based on the GPL-licensed AI Provider For DeepSeek 1.0.3, originally created by Sajjad Hossain Sagor. The original author's attribution and contribution are retained with thanks. Guducat / 孤独豹猫 is the current maintainer of this fork. The original project and this fork are distributed under GPL-2.0-or-later. See NOTICE.md and license.txt for details.

== Changelog ==

= 3.1.1 =

* Added the public roadmap to the English README, matching the Simplified Chinese README.

= 3.1.0 =

* Polished the DeepSeek Connector administrator interface with compact usage metrics, clearer model settings, and a collapsible custom pricing form.

= 0.3.0 =

* Added WordPress AI Request Logging status guidance and DeepSeek request cost estimation.
* Added versioned built-in and administrator-defined pricing rules.
* Added English and Simplified Chinese administration translations.

= 0.2.0 =

* Added the DeepSeek Connector management page with model capability overrides and one experimental model fallback.

= 0.1.5 =

* Renamed the plugin to AI Connector for DeepSeek Guducat.ver.
* Added administrator-only, click-to-query DeepSeek balance lookup.

= 0.1.0 =

* Established an independent plugin identity and namespace.
* Added a maintainable development and test toolchain.
