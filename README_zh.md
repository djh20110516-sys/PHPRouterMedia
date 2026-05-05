# PHPRouterMedia

**基于 [phpmediaserver](https://github.com/EsTass/phpmediaserver) 开发，针对受限设备优化。**

本版本专为解码能力受限的设备开发，如 iOS 设备、低端安卓盒子、媒体播放能力较弱的智能电视等。核心改进是增加了 **Remux 预缓存**（MKV→MP4 后台转封装）和 **HLS 串流**，让无法直接播放 MKV/HEVC 的设备也能流畅观看。

> 💙 **致谢**：特别感谢原项目作者 [@EsTass](https://github.com/EsTass) 创造了 phpmediaserver 这个优秀的开源项目。

---

## 播放模式说明

PHPRouterMedia 提供多种播放模式，适配不同设备的解码能力：

| 模式 | 按钮颜色 | 说明 |
|------|---------|------|
| **播放（软解）** | 🟢 绿色 | phpmediaserver 原版播放模式。由 PHP 提供文件服务，FFmpeg 实时软解码。**缺点**：服务器 CPU 占用高，且软解码会导致视频清晰度下降。不推荐低功耗服务器或高码率文件使用。 |
| **硬解（HW）** | 🟡 金黄色 | 使用硬件加速（AMD VAAPI / NVIDIA NVENC）进行解码/编码，CPU 占用大幅降低。需要服务器支持硬件转码。 |
| **Remux（流复制）** | 🟣 紫蓝色 | 不重新编码，直接复制视频/音轨流（`-c copy`）。转码 **零 CPU 占用**。在客户端支持原始编解码器时效果最佳，适合局域网内能力足够的设备。 |
| **HLS（HTTP 串流）** | 🔴 深粉色 | 输出 Apple 兼容的 HLS 流（`.m3u8` + `.ts` 分片）。**最适合 iOS（iPad/iPhone Safari）**及其他无法直接播放 MKV/HEVC 的受限客户端，串流效率高、开销低。 |
| **预缓存（Remux 缓存）** | ⚪ 灰色 | 后台将 MKV→MP4 转封装。完成后出现**播放缓存**按钮，以零 CPU 直接文件服务。适合解码能力弱的设备。 |

> 💡 **推荐**：iOS 设备使用 **HLS**，局域网能力足够的设备使用 **Remux**，确实需要时才使用 **硬解**。

## 功能特性

- **HTML5 网页播放器** — PHP7 + SQLite + jQuery + FFmpeg，支持音轨/字幕切换
- **Remux 预缓存** — 后台将 MKV/HEVC 转封装为 MP4，解码弱的设备可零 CPU 直连播放
- **HLS 串流** — 输出 Apple 兼容的 HLS 流，支持 iOS 及各类受限客户端
- **海报墙** — 按类型、演员、年份、评分浏览和筛选
- **智能分组** — 首播、继续观看、推荐、最近添加
- **实时转码** — FFmpeg 实时转码，无需预先转码或创建临时文件
- **元数据刮削** — 支持 OMDB、TheTVDB、IMDB（需配置 API Key）
- **DLNA 服务器** — 内置 mini DLNA 服务，已测试兼容 VLC 和 Android 客户端
- **多用户管理** — 管理员和普通播放用户
- **IP 控制** — 国家/IP 黑白名单，支持自动封禁
- **多语言** — 支持 English / Spanish / Chinese

## 技术栈

| 组件 | 技术 |
|------|------|
| 后端 | PHP 7+ |
| 数据库 | SQLite |
| 前端 | jQuery 3.2.1 + Lazy Load |
| 媒体处理 | FFmpeg / FFprobe |
| 串流 | DLNA (phpdlna)、HLS |

## 依赖要求

### PHP 扩展（必需）
```
curl        # HTTP 请求
iconv       # 字符编码转换
pdo_sqlite  # SQLite 数据库
sqlite3     # SQLite3 支持
sockets     # Socket 支持
xmlrpc      # XML-RPC
zip         # ZIP 解压
soap        # DLNA 服务器（可选）
```

### 系统依赖（必需）
```
ffmpeg >= 3.0
ffprobe
```

### 系统依赖（推荐）
```
filebot       # 媒体文件识别与重命名
wget          # 网络下载
pymediaident  # Python 媒体识别工具
```

### PHP 配置
在 `php.ini` 中设置 `open_basedir` 以允许访问媒体目录：
```ini
open_basedir = ":/path/to/phproutermedia/:/path/to/your/media"
```

## 安装部署

### 1. 部署代码
将项目文件复制到 Web 服务器目录，例如 `/var/www/phproutermedia/`。

### 2. 配置 Web 服务器

#### Apache 配置（端口 8100）

创建 `/etc/apache2/sites-available/phproutermedia.conf`：

```apache
# 监听 8100 端口
Listen 8100

<VirtualHost *:8100>
    ServerName 0.0.0.0
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/phproutermedia

    <Directory /var/www/phproutermedia>
        Options FollowSymlinks
        AllowOverride All
        Require all granted
        # 重要：允许 PHP 访问媒体目录
        # 添加所有存放媒体文件的路径
        php_admin_value open_basedir "/var/www/phproutermedia:/path/to/your/media:/path/to/downloads"
    </Directory>

    DirectoryIndex index.php

    ErrorLog ${APACHE_LOG_DIR}/phproutermedia-error.log
    CustomLog ${APACHE_LOG_DIR}/phproutermedia-access.log combined
</VirtualHost>
```

启用必需的 Apache 模块：
```bash
sudo a2enmod rewrite headers env
sudo a2ensite phproutermedia.conf
sudo systemctl restart apache2
```

> ⚠️ **关键**：`open_basedir` 必须包含所有 PHP 需要访问的目录（媒体文件、下载目录等）。如果未正确设置，文件浏览和播放将失败。

#### Nginx 配置示例（端口 8100）
```nginx
server {
    listen 8100;
    server_name your-domain;
    root /var/www/phproutermedia;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 3. 设置权限
```bash
chown -R www-data:www-data /var/www/phproutermedia/cache
chmod -R 755 /var/www/phproutermedia/cache
```

### 4. 配置文件
- **`config.php`** — 基础配置（媒体路径、语言等）
- **`config.ws.php`** — 本地个性化覆盖配置（API Key 等，**不提交到 Git**）

### 5. 重启 Web 服务器
```bash
# Apache
sudo systemctl restart apache2

# Nginx
sudo systemctl restart nginx
```

### 6. 首次登录
```
用户名：admin
密码：admin01020304
```
**首次登录后请立即修改密码！**

## 默认端口

项目默认使用 **8100** 端口，可在 Web 服务器配置中修改。

## 项目结构

```
PHPRouterMedia/
├── actions/        # 后端接口（播放、刮削、DLNA 等）
├── cache/          # 缓存目录（海报、字幕、临时文件等）
├── config.php       # 主配置文件
├── config.ws.php    # 本地覆盖配置（不提交到 git）
├── core/           # 核心函数（刮削器、工具类）
├── dlna/           # DLNA 服务器实现
├── imgs/           # 图片资源
├── js/             # JavaScript 库
├── lang/           # 多语言文件（en/es/zh）
├── index.php       # 入口页面
└── README.md
```

## 原项目

本项目基于 [@EsTass](https://github.com/EsTass) 的 [phpmediaserver](https://github.com/EsTass/phpmediaserver) 开发。感谢原作者的扎实基础和开源贡献，让本分支成为可能。

## `.htaccess` — MIME 类型（对播放至关重要）

项目根目录包含 `.htaccess` 文件，其中设置了媒体播放所需的 MIME 类型。**请勿删除此文件。**

播放所需的关键 MIME 类型：

```apache
# HLS 串流（iOS/Safari）
AddType application/x-mpegURL .m3u8
AddType video/MP2T .ts

# M(PEG)-DASH
AddType application/dash+xml .mpd

# 字幕
AddType text/vtt .vtt
AddType text/srt .srt

# 视频
AddType video/mp4 .mp4
AddType video/webm .webm
```

如遇到播放失败，请检查：
1. Apache 已设置 `AllowOverride All`（确保 `.htaccess` 被读取）
2. `mod_mime` 已启用（`a2enmod mime`）
3. 项目根目录存在 `.htaccess` 文件

## 许可证

详见 `LICENSE` 文件。
