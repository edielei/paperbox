# PaperBox 纸质文档管理系统

一个轻量级的个人/家庭纸质文档电子化管理系统，基于 PHP + SQLite 构建，可部署在群晖 NAS 或任意支持 PHP 的环境中。数据完全本地存储，隐私安全可控。

![License](https://img.shields.io/badge/license-MIT-blue.svg) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-green.svg) ![SQLite](https://img.shields.io/badge/SQLite-3-blue.svg)

## ✨ 功能特性

### 📄 文档管理
- **多分类管理**：票据、合同、证件、说明书、医疗、保险、学校、银行、其他
- **多图片上传**：支持 jpg/png/gif/webp，单张最大 20MB
- **拖拽排序**：添加和编辑时均可拖拽调整图片顺序，支持手机端触摸操作
- **智能图片布局**：单张铺满、两张各 50%、三张各 1/3，超过 6 张默认折叠可展开

### ✍️ Markdown 编辑器
- **双模式切换**：可视化编辑 / 源码编辑，实时预览
- **完整语法支持**：标题、引用、表格、代码块、有序/无序列表（支持嵌套）
- **GitHub 风格警告框**：注意（蓝）、提示（绿）、重要（紫）、警告（橙）、危险（红）
- **扩展行内格式**：下划线 `++text++`、高亮 `==text==`、上标 `^text^`、下标 `~text~`
- **内置语法帮助页**：15 个示例卡片，含代码和实时渲染效果
- **快捷键**：电脑端 `Ctrl + Enter` 快速提交

### 🔍 搜索与筛选
- **关键词搜索**：匹配标题和内容，结果高亮显示
- **分类筛选**：下拉切换即筛选，无需点击搜索按钮
- **标签多选**：多标签为 AND 关系（同时包含所选标签才显示）
- **状态保留**：切换分类时保留当前搜索关键词和已选标签

### 🖼️ 图片查看器
- 点击图片放大查看
- 电脑端：鼠标滚轮缩放、拖拽平移、键盘左右导航
- 手机端：新窗口打开原图
- 展开全部图片按钮

### 📱 响应式设计
- 电脑端：图片三列布局
- 手机端：图片两列布局
- 所有页面自适应，分页器手机端不换行

### 🔒 安全与性能
- CSRF 令牌防护
- SQLite WAL 模式 + 索引优化，支持十万级数据
- 上传目录防浏览
- 自动链接白名单，防止 XSS

## 🛠️ 技术栈

| 层级 | 技术 |
|------|------|
| 后端 | PHP 7.4+ |
| 数据库 | SQLite 3 |
| 前端 | 原生 HTML / CSS / JavaScript |
| 部署 | 群晖 NAS / 任意 PHP 环境 |
| 设计参考 | LuLu UI Edge |

## 📦 安装部署

### 环境要求
- PHP 7.4 或更高版本
- 启用 `pdo_sqlite` 扩展
- 启用 `gd` 扩展（图片处理）

### 群晖 NAS 部署
1. 打开「套件中心」，安装 **Web Station** 和 **PHP 7.4**（或更高版本）
2. 在 Web Station 中创建虚拟主机，指向项目目录
3. 将项目文件上传到 NAS 的网页根目录
4. 确保 `uploads/` 目录和 `paper.db` 有写入权限
5. 浏览器访问 `http://NAS_IP:端口` 即可使用

### 通用部署
```bash
# 克隆项目
git clone https://github.com/edielei/paperbox.git
cd paperbox

# 确保目录权限
chmod 755 uploads/
chmod 666 paper.db 2>/dev/null || true

# 配置 Web 服务器（Nginx / Apache）指向项目目录
```

首次访问时，系统会自动创建数据库和数据表。

## 📂 目录结构

```
paperbox/
├── index.php              # 首页（列表、搜索、筛选、分页）
├── view.php               # 文档详情页
├── add.php                # 添加文档
├── edit.php               # 编辑文档
├── delete.php             # 删除文档（POST + CSRF）
├── preview.php            # Markdown 预览 AJAX 接口
├── upload.php             # 图片上传接口
├── markdown-help.php      # Markdown 语法帮助页
├── config.php             # 数据库初始化与公共函数
├── markdown.php           # SimpleMD Markdown 解析器
├── css/
│   └── style.css          # 全部样式
├── js/
│   ├── common.js          # 公共脚本（更多菜单、标签面板）
│   ├── editor.js          # 编辑器脚本（上传、拖拽、预览、快捷键）
│   └── viewer.js          # 图片查看器（缩放、拖拽、导航）
├── uploads/               # 图片存储目录
├── favicon.ico            # 网站图标
├── favicon.svg            # SVG 图标
└── README.md
```

## 📝 Markdown 语法速查

### 基础语法
```markdown
# 一级标题
## 二级标题

> 引用文本

| 列1 | 列2 |
| --- | --- |
| 内容 | 内容 |

- 无序列表
  - 嵌套列表
1. 有序列表
2. 第二项

```代码块```
```

### 警告框
```markdown
> [!NOTE]
> 这是注意信息

> [!TIP]
> 这是提示信息

> [!IMPORTANT]
> 这是重要信息

> [!WARNING]
> 这是警告信息

> [!CAUTION]
> 这是危险信息
```

## 🎨 设计规范

- 圆角：4px
- 组件高度：40px
- 主色调：`#2a80eb`（明亮蓝）
- 边框色：`#d0d0d5`
- Header：白底 + 8px 圆角 + 1px 浅灰边框 + 轻微阴影
- 字体：系统默认无衬线字体

## 📄 许可证

本项目基于 MIT 许可证开源，详见 [LICENSE](LICENSE) 文件。

## 🙏 致谢

- [LuLu UI Edge](https://l-ui.com/) — 设计参考
- AI 辅助开发 — 提升开发效率

---

**把家里那些杂七杂八的纸张，拍照记录整理上来吧。**
