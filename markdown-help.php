<?php
require_once 'config.php';
require_once 'markdown.php';
$md = new SimpleMD();

// 语法示例数据：[标题, 说明, 代码, 是否渲染]
$examples = [
    ['标题', '# 一级标题<br>## 二级标题<br>### 三级标题', "# 一级标题\n## 二级标题\n### 三级标题"],
    ['文字格式', '**粗体** *斜体* ~~删除线~~ ++下划线++ ==高亮== ^上标^ ~下标~ `行内代码`', "**粗体** *斜体* ~~删除线~~ ++下划线++ ==高亮== ^上标^ ~下标~ `行内代码`"],
    ['无序列表', "- 项目一\n- 项目二\n  - 子项目\n  - 子项目", "- 项目一\n- 项目二\n  - 子项目\n  - 子项目"],
    ['有序列表', "1. 第一步\n2. 第二步\n3. 第三步", "1. 第一步\n2. 第二步\n3. 第三步"],
    ['嵌套列表', "- 父项\n  1. 子项一\n  2. 子项二\n- 父项二", "- 父项\n  1. 子项一\n  2. 子项二\n- 父项二"],
    ['引用', "> 这是一段引用文字", "> 这是一段引用文字"],
    ['链接和图片', '[链接文字](https://example.com)', "[链接文字](https://example.com)"],
    ['代码块', "```php\necho 'Hello';\n```", "```php\necho 'Hello';\n```"],
    ['表格', "| 姓名 | 年龄 |\n| --- | --- |\n| 张三 | 25 |\n| 李四 | 30 |", "| 姓名 | 年龄 |\n| --- | --- |\n| 张三 | 25 |\n| 李四 | 30 |"],
    ['分隔线', "上面\n---\n下面", "上面\n\n---\n\n下面"],
    ['警告框', "五种类型：注意/提示/重要/警告/危险", "> [!NOTE]\n> 这是注意信息\n\n> [!TIP]\n> 这是提示建议\n\n> [!IMPORTANT]\n> 这是重要信息\n\n> [!WARNING]\n> 这是警告内容\n\n> [!CAUTION]\n> 这是危险提示"],
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Markdown 语法帮助</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .help-section {
            background: #fff;
            border: 1px solid #e8e9eb;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }
        .help-section h3 {
            margin: 0 0 10px;
            font-size: 1rem;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .help-section > h3::before {
            content: '';
            width: 4px;
            height: 16px;
            background: #2a80eb;
            border-radius: 2px;
        }
        .help-code {
            background: #f5f6f8;
            border: 1px solid #e8e9eb;
            border-radius: 6px;
            padding: 10px 14px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.85rem;
            color: #333;
            white-space: pre-wrap;
            word-break: break-all;
            margin-bottom: 10px;
            line-height: 1.6;
        }
        .help-preview {
            border: 1px dashed #d0d0d5;
            border-radius: 6px;
            padding: 10px 14px;
            background: #fafbfc;
        }
        .help-preview .markdown-body { margin: 0; }
        .help-preview .markdown-body p:first-child { margin-top: 0; }
        .help-preview .markdown-body p:last-child { margin-bottom: 0; }
        .help-intro {
            background: #f0f6ff;
            border-left: 4px solid #2a80eb;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 18px;
            color: #333;
            font-size: 0.92rem;
            line-height: 1.7;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Markdown 语法帮助</h1>
    </div>

    <div class="help-intro">
        本系统支持常用的 Markdown 语法，在编辑文档内容时可以直接使用。下方列出了所有支持的语法及渲染效果，点击「编辑」页的「预览」标签可实时查看效果。
    </div>

    <?php foreach ($examples as $ex): ?>
    <div class="help-section">
        <h3><?= $ex[0] ?></h3>
        <div class="help-code"><?= htmlspecialchars($ex[2], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="help-preview">
            <div class="markdown-body"><?= $md->text($ex[2]) ?></div>
        </div>
    </div>
    <?php endforeach; ?>

</div>
<footer class="site-footer">
    <p>PaperBox 纸质文档管理系统 &copy; <?php echo date('Y'); ?></p>
</footer>
</body>
</html>
