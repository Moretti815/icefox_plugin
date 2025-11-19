# IceFox 插件

[![Typecho Version](https://img.shields.io/badge/Typecho-1.2.0+-blue.svg)](https://typecho.org)
[![PHP Version](https://img.shields.io/badge/PHP-7.0+-green.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

IceFox 插件是专为 IceFox 主题设计的 Typecho 博客系统功能扩展插件，提供了文章管理增强、点赞系统、置顶功能等多种实用功能。

## ✨ 主要功能

### 📝 文章管理增强
- **文章置顶功能** - 支持在管理后台一键置顶/取消置顶文章
- **增强的管理界面** - 优化了文章列表管理页面，提供更直观的操作体验
- **状态管理** - 支持文章多种状态管理（公开、待审核、隐藏、私密）

### 👍 点赞系统
- **文章点赞功能** - 访客可以对文章进行点赞/取消点赞操作
- **防重复点赞** - 通过 IP 地址和用户信息防止重复点赞
- **点赞统计** - 实时统计每篇文章的点赞数量
- **用户区分** - 区分登录用户和游客的点赞记录

### 🗄️ 数据库扩展
- **icefox_archive 表** - 存储文章扩展信息（置顶状态、点赞数等）
- **icefox_likes 表** - 存储点赞记录详情
- **自动升级** - 支持数据库结构自动检查和升级

## 🚀 快速开始

### 环境要求
- PHP 7.0 或更高版本
- Typecho 1.2.0 或更高版本
- MySQL 数据库支持

### 安装步骤

1. **下载插件**
   ```bash
   git clone https://gitee.com/xiaopanglian/icefox_plugin.git
   ```

2. **上传插件**
   将整个插件文件夹上传到 Typecho 安装目录下的 `usr/plugins/icefox/`

3. **启用插件**
   - 登录 Typecho 管理后台
   - 进入「控制台」→「插件管理」
   - 找到 IceFox 插件并点击「启用」

4. **完成安装**
   插件会自动创建所需的数据表，安装完成后即可使用所有功能。

## 📖 使用说明

### 文章置顶功能
1. 在管理后台进入「管理」→「文章」
2. 在文章列表中找到需要置顶的文章
3. 点击该文章操作列中的「置顶」按钮
4. 置顶成功后按钮会显示为「取消置顶」并标记为红色

### 点赞功能使用
插件提供了 API 接口供前端调用：

#### 获取文章点赞信息
```javascript
fetch('/action/icefox?do=getLikes&cid=文章ID')
  .then(response => response.json())
  .then(data => {
    console.log('点赞数:', data.likes);
    console.log('是否已点赞:', data.isLiked);
  });
```

#### 切换点赞状态
```javascript
fetch('/action/icefox?do=like&cid=文章ID', {
  method: 'POST'
})
  .then(response => response.json())
  .then(data => {
    console.log('操作结果:', data.message);
    console.log('当前点赞数:', data.likes);
  });
```

## 📁 项目结构

```
icefox/
├── Plugin.php              # 插件主文件
├── Action.php              # 动作处理器
├── admin/
│   └── manage-posts.php    # 增强的文章管理页面
├── README.md               # 项目说明文档
└── .claude/                # Claude 配置文件
    └── settings.local.json
```

### 文件说明

- **Plugin.php** - 插件的核心文件，包含插件的激活、禁用、配置等功能
- **Action.php** - 处理所有 AJAX 请求，包括点赞、置顶等操作
- **admin/manage-posts.php** - 增强的文章管理界面，添加了置顶功能按钮

## 🔧 API 接口

插件提供了以下 API 接口，所有接口都通过 `/action/icefox` 路径访问：

### 点赞相关接口

#### 获取点赞信息
- **URL**: `/action/icefox?do=getLikes&cid={文章ID}`
- **方法**: GET
- **返回**:
  ```json
  {
    "success": true,
    "likes": 10,
    "isLiked": false
  }
  ```

#### 切换点赞状态
- **URL**: `/action/icefox?do=like&cid={文章ID}`
- **方法**: POST
- **返回**:
  ```json
  {
    "success": true,
    "message": "点赞成功",
    "isLiked": true,
    "likes": 11
  }
  ```

### 管理员接口（需要管理员权限）

#### 设置文章置顶
- **URL**: `/action/icefox?do=top&cid={文章ID}&stat={当前状态}`
- **方法**: GET
- **参数**:
  - `cid`: 文章ID
  - `stat`: 当前置顶状态（0或1）

## 🗄️ 数据库结构

### icefox_archive 表
| 字段名 | 类型 | 说明 |
|--------|------|------|
| cid | int(10) unsigned | 文章ID（主键） |
| is_top | tinyint(1) | 是否置顶（0=否，1=是） |
| likes | int(10) unsigned | 点赞总数 |

### icefox_likes 表
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | int(10) unsigned | 记录ID（自增主键） |
| cid | int(10) unsigned | 文章ID |
| uid | int(10) unsigned | 用户ID（登录用户） |
| author | varchar(150) | 用户昵称 |
| mail | varchar(200) | 用户邮箱 |
| ip | varchar(45) | IP地址 |
| created_at | int(10) unsigned | 点赞时间戳 |

## 🔒 安全性

- 点赞功能通过 IP 地址和用户信息防止重复操作
- 管理员操作需要验证用户权限
- 所有数据库查询都使用参数化查询防止 SQL 注入
- 插件会自动检查 PHP 和 Typecho 版本兼容性

## 🔄 版本兼容性

| 插件版本 | Typecho 版本 | PHP 版本 |
|----------|--------------|----------|
| 1.0.0 | 1.2.0+ | 7.0+ |

## 🐛 问题反馈

如果您在使用过程中遇到问题，请通过以下方式反馈：

- 提交 Issue：https://gitee.com/xiaopanglian/icefox_plugin/issues
- 作者博客：https://xiaopanglian.com

## 📝 更新日志

### v1.0.0 (2024-11-19)
- ✨ 初始版本发布
- 🎯 实现文章置顶功能
- 👍 实现文章点赞系统
- 🔧 增强的文章管理界面
- 🗄️ 自动创建和升级数据库结构

## 👨‍💻 作者

- **作者**: 小胖脸
- **主页**: https://xiaopanglian.com
- **邮箱**: 请通过作者博客联系

## 📄 许可证

本项目采用 MIT 许可证，详情请查看 [LICENSE](LICENSE) 文件。

## 🙏 致谢

- 感谢 Typecho 团队提供的优秀博客系统
- 感谢所有用户的支持和反馈

---

> 💡 **提示**: 本插件需要与 IceFox 主题配合使用以获得最佳体验。