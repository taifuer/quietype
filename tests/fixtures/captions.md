图片在下方显示题注，表格在上方显示标题。编号可手动填写，也可以只写说明。

![起伏的山坡与远山](https://images.example.test/captions/lead.jpg)

[!CAPTION] 图 1：**山野之间**，保留 `alt` 作为独立描述。[资料来源](https://example.com/source "图片资料")。

## 表格题注

| 方案 | 特点 |
| --- | --- |
| 原生加载 | 首屏正常加载，其余按需读取 |
| 题注 | 服务端输出，不依赖 JavaScript |

[!CAPTION] 表 1：*阅读功能*对比

<table id="caption-wide-table" style="min-width: 1000px"><thead><tr><th>参数</th><th>说明</th></tr></thead><tbody><tr><td>width / height</td><td>为图片预留正确的空间，宽表格仍可以独立横向滚动。</td></tr></tbody></table>

[!CAPTION] 表 2：宽表格与移动端阅读

## 已有题注与普通图片

<figure><img src="https://images.example.test/captions/native.jpg" alt="原生 figure 中的山林"><figcaption>原生图片题注继续保留。</figcaption></figure>

[caption align="alignnone" width="960"]<img src="https://images.example.test/captions/wordpress.jpg" alt="WordPress 原生图片"> WordPress 原生图片说明。[/caption]

![只有替代文字，不应自动产生题注](https://images.example.test/captions/plain.jpg)

## 链接图片与长说明

[![可打开原图的山间小路](https://images.example.test/captions/linked.jpg)](https://images.example.test/captions/full.jpg)

[!CAPTION] 这是一段较长的说明，用来确认手机上的题注能够自然换行，不会撑开正文。也可以注明来源：[https://example.com/a-very-long-image-source-without-any-natural-word-boundaries/reading-photography-and-quiet-moments](https://example.com/a-very-long-image-source-without-any-natural-word-boundaries/reading-photography-and-quiet-moments)。

## 标记示例不参与渲染

```text
[!CAPTION] 代码示例保持原样。
![示例图片](example.jpg)
[!CAPTION] 这不是实际题注。
```

下面保留一个测试用的空白区，用于验证远处图片不会在首次打开页面时下载。

<div class="caption-test-spacer" style="height: 6000px" aria-hidden="true"></div>

![滚动接近后加载的远处图片](https://images.example.test/captions/distant.jpg)

[!CAPTION] 图 2：滚动接近后正常显示，也可以在灯箱中直接切换到这张图。
