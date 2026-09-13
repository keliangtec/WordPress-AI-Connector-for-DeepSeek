[English](README.md)

# DeepSeek AI Provider for WordPress

DeepSeek AI Provider for WordPress 将 DeepSeek 注册为 WordPress AI Client 的提供商。

## 功能

- 通过 `GET /v1/models` 动态发现可用模型。
- 通过 WordPress AI Client 支持文本生成、聊天历史、函数调用和 DeepSeek 推理历史回放。
- 仅对精确且区分大小写的模型 ID `deepseek-flash` 声明支持文本和图片输入。其他当前模型及任何未知的未来模型默认仅支持文本输入。
- 将远程 URL 或 `data:` URI 形式的图片输入经 WordPress PHP AI Client 传给 DeepSeek 的 OpenAI 兼容 Chat Completions 端点。
- 由 WordPress AI Client connector 管理 DeepSeek API key。

本插件当前不提供 WordPress 媒体库 UI、上传流程、Files API 集成、图片 `detail` 控制或 DeepSeek Responses API 支持。生成请求当前固定使用 OpenAI 兼容的 Chat Completions 端点。本插件不会绕过 WordPress PHP AI Client 独立上传图片。

## 环境要求

- WordPress 7.0 或更高版本，并且 WordPress AI Client 可用。
- PHP 7.4 或更高版本。生产目标环境为 PHP 8.4。
- 在 WordPress AI Client connector 中配置 DeepSeek API key。

## 安装

1. 将插件目录放入 `wp-content/plugins/`。
2. 启用 **DeepSeek AI Provider for WordPress**。
3. 通过 WordPress AI Client 配置 DeepSeek connector。

## 图片输入

`wordpress/php-ai-client` 中的 OpenAI 兼容父类负责将 `File` 图片输入序列化为 `image_url` 内容块。本插件负责声明哪些 DeepSeek 模型支持图片输入，并处理 DeepSeek 特有的端点和请求差异；本插件自身不编码图片。只有当 AI Client 选中的模型已声明支持图片输入时，才会使用图片传输；本插件当前仅为精确的模型 ID `deepseek-flash` 做此声明。

## 开发

使用 Composer 安装开发依赖，然后运行：

```bash
composer validate --strict
composer test
composer phpcs
composer phpstan
```

CI 覆盖最低支持的 PHP 7.4 和生产目标 PHP 8.4。

## 外部服务

本插件会将模型列表请求和 AI 提示发送到 `https://api.deepseek.com/v1` 下的 DeepSeek API。模型发现使用 `GET /v1/models`；生成请求使用 OpenAI 兼容的 Chat Completions 端点。请求包含 WordPress AI Client connector 管理的 API key 和用于处理的提示内容；存在图片 URL 或 data URI 时，这些值也会被发送。DeepSeek 的条款和隐私政策适用于该服务处理的数据。

## 致谢与许可证

本项目基于 **Sajjad Hossain Sagor** 原始创建的 GPL 许可插件 **AI Provider For DeepSeek 1.0.3**。本项目保留并感谢原作者的贡献。原项目和本 fork 均以 **GPL-2.0-or-later** 许可证发布。详见 `NOTICE.md` 和 `license.txt`。
