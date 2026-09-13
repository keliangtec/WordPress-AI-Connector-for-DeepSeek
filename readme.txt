=== DeepSeek AI Provider for WordPress ===
Contributors: sajjad67
Tags: ai, deepseek, artificial-intelligence, connector
Requires at least: 7.0
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Registers DeepSeek as a provider for the WordPress AI Client.

== Description ==

DeepSeek AI Provider for WordPress registers DeepSeek with the WordPress AI Client included in WordPress 7.x.

Features:

* Dynamic model discovery with `GET /v1/models`.
* OpenAI-compatible text generation, chat history, function calling, and DeepSeek reasoning-history replay.
* Text and image input are advertised only for the exact, case-sensitive model ID `deepseek-flash`; every other current or unknown model defaults to text-only input.
* Remote URL and `data:` URI image inputs are passed through the WordPress PHP AI Client to the OpenAI-compatible Chat Completions endpoint.
* API key management through the WordPress AI Client connector system.

This plugin does not currently provide a Media Library UI, an upload workflow, Files API integration, image `detail` controls, or DeepSeek Responses API support. Generation requests are currently fixed to the OpenAI-compatible Chat Completions endpoint. The plugin does not upload images independently of the WordPress PHP AI Client.

Requirements:

* WordPress 7.0 or later, with the WordPress AI Client available.
* PHP 7.4 or later. The production target environment is PHP 8.4.
* A DeepSeek API key configured in the WordPress AI Client connector.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Activate DeepSeek AI Provider for WordPress.
3. Configure DeepSeek through the WordPress AI Client connector.

== Frequently Asked Questions ==

= How do I get a DeepSeek API key? =

Create an API key through the DeepSeek Platform.

== External Services ==

This plugin connects to the DeepSeek API at `https://api.deepseek.com/v1` to discover models and process AI prompts. The API key managed by WordPress and prompt content supplied for processing are sent to DeepSeek when these features are used.

Model discovery uses `GET /v1/models`. Generation requests use the OpenAI-compatible Chat Completions endpoint. When the WordPress PHP AI Client supplies image inputs as remote URLs or `data:` URIs, those values are included in the request. The plugin does not provide a separate image uploader, Media Library UI, Files API integration, or image `detail` control.

Service provider: DeepSeek

* Website: https://www.deepseek.com/
* Terms: https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html
* Privacy policy: https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html

== Credits ==

Based on the GPL-licensed AI Provider For DeepSeek 1.0.3, originally created by Sajjad Hossain Sagor. The original author's attribution and contribution are retained with thanks. Guducat / 孤独豹猫 is the current maintainer of this fork. The original project and this fork are distributed under GPL-2.0-or-later. See NOTICE.md and license.txt for details.

== Changelog ==

= 0.1.0 =

* Established an independent plugin identity and namespace.
* Added a maintainable development and test toolchain.
