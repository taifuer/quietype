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
- 基于 PhotoSwipe 的图片预览，支持按钮、滚轮、双击和触屏缩放
- 分类、标签、搜索、年度归档、友链、关于、评论与 404 页面
- 固定移动 Header、抽屉菜单、遮罩关闭与返回顶部
- 打印样式、键盘焦点与 `prefers-reduced-motion` 支持
- 不写入主题私有短代码，避免内容锁定

## 环境要求

- WordPress 6.4+
- PHP 8.0+

主题可独立使用。WP Editor.md、KaTeX 和 Prism 的兼容处理用于已有 Markdown 技术博客，并不是主题运行的强制依赖。

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

页脚的 GitHub 和联系邮箱可在“外观 → 自定义 → 页脚联系方式”中修改；将字段留空即可隐藏对应图标。

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
