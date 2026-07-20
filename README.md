# Quietype

> A quiet WordPress theme for thoughtful reading.

Quietype 是一款为中文长文与技术写作设计的经典 WordPress 主题。它以简约、素雅和持续阅读为核心，同时完整处理 Markdown 常见元素、技术文章目录、代码、公式与移动端导航。

![Quietype 首页预览](screenshot.png)

## 下载与安装

推荐从 [GitHub Releases](https://github.com/taifuer/quietype/releases) 下载最新的 `quietype-<版本号>.zip`，然后在 WordPress 后台进入“外观 → 主题 → 安装主题 → 上传主题”，选择压缩包并启用。发布包已经包含顶层 `quietype/` 目录，可以直接安装，无需手工改名。

也可以将仓库克隆到 WordPress 主题目录：

```bash
cd wp-content/themes
git clone git@github.com:taifuer/quietype.git
```

启用后，WordPress 使用的主题目录标识为 `quietype`。

## 特性

- 时间清单式首页，日期、分类、标签与浏览量保持清晰层级
- 800px 长文阅读区，适配中英文混排的系统字体栈
- H2/H3 自动目录、正文阅读进度、移动端折叠目录和阅读工具栏
- 纸白、米杏、浅绿三种亮色阅读背景
- 低干扰亮色代码块、语法高亮、复制按钮和表格横向滚动
- 图片边框、图注、引用、列表、脚注、KaTeX 与响应式媒体样式
- 可配置的文章末尾 CC BY-NC-SA 4.0 署名与来源声明
- 正文图片原生懒加载、远程图片宽高缓存和替代文字发布检查
- 基于 PhotoSwipe 的图片预览，支持按钮、滚轮、双击和触屏缩放
- 分类、标签、搜索、年度归档、友链、关于、评论与 404 页面
- 友链状态管理、分批可达性检查与人工确认的失联标记
- 与前台一致的登录、找回密码和重置密码界面
- 固定移动 Header、抽屉菜单、遮罩关闭与返回顶部
- 打印样式、键盘焦点与 `prefers-reduced-motion` 支持
- 不写入主题私有短代码，避免内容锁定

## 环境要求

- WordPress 6.4+
- PHP 8.0+

主题可独立使用。WP Editor.md、KaTeX 和 Prism 的兼容处理用于已有 Markdown 技术博客，并不是主题运行的强制依赖。Quietype 会检查当前正文，仅在确实存在代码、公式、图片或图表时保留对应资源；普通页面和纯文本文章不会加载整套编辑器前端依赖。

## 菜单与页面

主题提供两个菜单位置：

- `primary`：顶部主导航
- `footer`：页脚导航

内置页面模板：

- `template-archives.php`：年度文章归档
- `template-links.php`：友情链接
- `list-archive.php`：兼容归档模板
- `list-tag.php`：兼容旧站曾分配的标签模板；标签入口已整合进文章归档

普通页面、分类、标签、搜索和 404 使用 WordPress 模板层级自动匹配。

## 阅读背景

背景选择保存在浏览器 `localStorage` 的 `quietype-reading-bg` 中，不写 Cookie 或数据库：

- `paper`：纸白
- `warm`：米白
- `green`：浅绿

Quietype 目前只提供亮色阅读背景。

Quietype 自有配置集中在“外观 → Quietype 设置”，包括页脚联系方式与备案号、文章版权声明、SEO、国内访问、WordPress 优化、登录安全、SMTP 通知和自定义代码。WordPress 原生的站点标题、菜单和额外 CSS 仍使用各自原生界面。旧版本保存在 Customizer 中的 Quietype 配置会自动迁移。

统计脚本和站点验证代码可在“外观 → Quietype 设置”中分别添加到 Head 或 Footer。该页面使用 WordPress 自带代码编辑器，避免脚本在 Customizer 实时预览中意外执行。只有具备 `unfiltered_html` 权限的管理员可以保存可执行代码；样式仍建议优先使用 WordPress 原生“额外 CSS”。

## 友链状态

Quietype 启用期间会恢复 WordPress 原生“链接”管理入口，并为站点管理员提供友链管理权限，不永久修改角色数据。链接编辑页包含 Quietype 状态框，可将友链设为正常、待确认或失联，也可立即执行一次安全的公网 HTTP(S) 检测。主题默认每天轮换检测至多五条可见友链；连续失败三次仅进入后台“待确认”，不会自动在前台显示失联。管理员确认并保存“失联”后，友链卡片才显示低调角标；恢复为“正常”即可移除。

检测开关位于“外观 → Quietype 设置 → 站点与页脚”。状态保存在 WordPress Options 中，不修改核心链接表；切换主题后数据仍保留，但其他主题不会展示 Quietype 状态。

## SEO

主题在没有检测到 Yoast SEO、Rank Math、All in One SEO、SEOPress 或 The SEO Framework 时输出轻量元数据：description、keywords、Open Graph、社交摘要和 Schema.org JSON-LD。文章描述按“文章 SEO 自定义描述 → 手工摘要 → 自动摘要”取值，关键词按“文章 SEO 自定义关键词 → 标签 → 分类 → 站点关键词”取值。社交图片按“特色图 → 第一张正文图片 → 默认分享图”取值。文章和页面编辑页中的“Quietype SEO”区域可以覆盖自动结果。

后台可填写站点级描述和关键词。`meta keywords` 对主流搜索引擎的作用已经很小，只作为兼容字段保留；描述、规范标题、结构化数据和可读内容更重要。启用专用 SEO 插件后，Quietype 会停止输出整组 SEO 元数据，避免重复。

## 中国大陆访问优化

评论头像默认将 WordPress 生成的 Gravatar 地址替换为 `https://gravatar.loli.net/avatar/`，不修改用户或评论数据。可在“外观 → Quietype 设置”中修改地址或留空以保留 WordPress 原始地址；开发者也可继续通过过滤器覆盖：

```php
add_filter( 'quietype_avatar_base_url', function () {
	return 'https://secure.gravatar.com/avatar/';
} );
```

Prism 只负责语法解析、语言标记和行号；Quietype 内置亮色 token 配色与中文复制按钮，不依赖远程代码样式或剪贴板 CDN。

## 登录入口与安全

Quietype 会重设 WordPress 登录相关页面的视觉样式，并可在“外观 → Quietype 设置 → 安全”中启用自定义入口参数、一次性算术验证码和 XML-RPC 认证保护。例如参数名为 `entry`、参数值为一个至少 24 位的私有随机字符串时，初始入口是 `wp-login.php?entry=<私有值>`；验证成功后主题会换取一个 12 小时、HttpOnly、SameSite 的签名 Cookie，并跳转到不含入口值的登录地址。默认入口、错误参数和未登录的 `/wp-admin/` 均返回 404。请勿将真实入口值写入公开仓库、截图、统计平台或文档。

启用前请先保存完整入口地址。参数值留空时入口保护保持关闭；需要文件级配置时，也可以在 `wp-config.php` 中定义 `QUIETYPE_LOGIN_GATE_KEY` 和 `QUIETYPE_LOGIN_GATE_VALUE`，常量优先于后台字段。找回密码、密码重置、退出登录和 WordPress 签名恢复链接均保留兼容处理。

验证码和入口保护默认关闭，需由站点管理员明确启用。若站点不使用 WordPress App 或旧式远程发布，可以同时关闭 XML-RPC 认证与 Pingback，避免它绕过网页验证码。隐藏入口和简单验证码主要用于降低自动扫描噪声，不能替代强密码、双重验证和服务器限速。由于入口功能由主题提供，切换主题会恢复 WordPress 默认登录行为。

公开评论使用十分钟有效、提交后立即作废的四位数字验证，并配有蜜罐和短时提交节流。服务器仍应对登录、评论和 XML-RPC 配置独立频率限制。

## SMTP 与通知

“外观 → Quietype 设置 → 邮件”可以让 WordPress 内置 `wp_mail()` 使用 SMTP，并发送测试邮件。支持 TLS、SMTPS 和无加密连接、可选身份验证、自定义发件地址，以及管理员成功登录和新评论通知。登录通知仅针对拥有站点管理权限的账户并设有五分钟防重复；评论通知忽略垃圾评论、Pingback 和 Trackback，也会避开 WordPress 已发送给同一管理员的通知。

SMTP 密码会以原值保存在 WordPress 数据库中。优先使用邮箱服务商提供的独立授权码；若不希望密码进入数据库，可在 `wp-config.php` 定义 `QUIETYPE_SMTP_PASSWORD`。设置页会保留最近一次发信错误一天，但不会记录密码。SMTP 只负责把邮件交给服务器，正式使用前仍应配置 SPF、DKIM 和 DMARC，并通过测试邮件确认投递。

## WordPress 优化

“外观 → Quietype 设置 → WordPress”可以隐藏登录用户在网站前台看到的管理工具栏，以及停止为文章和页面创建新的历史版本；后台工具栏和自动保存不受影响。历史版本开关不会静默删除已有数据，需要管理员在同一设置页核对数量并单独确认删除。

## 内容兼容

主题通过标准 `the_content` 渲染文章，不修改编辑器保存的 Markdown 原文。对于 WP Editor.md，Markdown 仍保存在 `post_content_filtered`，主题只负责前台输出样式。

文章目录由渲染后的 H2/H3 自动生成，不要求修改历史文章，也不会把锚点写回数据库。

## 开发

当前主题不需要 Node.js 构建步骤。修改 PHP、CSS 或 JavaScript 后，可直接在本地 WordPress 环境中预览。

- 根目录 PHP 文件：WordPress 模板层级与主题功能
- `template-parts/`：可复用的文章列表组件
- `assets/js/`：交互逻辑与图片预览
- `assets/vendor/`：随主题分发的第三方前端依赖及许可证
- `style.css`：主题声明与前台样式
- `theme.json`：编辑器基础设置
- `screenshot.png`：WordPress 后台主题预览图

发布前建议至少回归以下内容：

- 长代码与中文注释
- 公式、表格和嵌套列表
- 多图、宽图、图注和历史 HTML
- 360px、390px、768px、1280px 与 1440px 布局
- 键盘导航、打印和关闭 JavaScript 后的正文可用性

## 许可证

Quietype 基于 [GNU General Public License v2 or later](LICENSE.txt) 发布。

主题内置的 PhotoSwipe 5.4.4 依据其 [MIT License](assets/vendor/photoswipe/LICENSE) 分发。
