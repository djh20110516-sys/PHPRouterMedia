# PHPRouterMedia

**A fork of [phpmediaserver](https://github.com/EsTass/phpmediaserver), optimized for restricted devices.**

This version is specifically developed for devices with limited decoding capabilities, such as iOS devices, low-end Android boxes, and smart TVs with weak media playback support. It focuses on **remux caching** (MKV→MP4) and **HLS streaming** to enable smooth playback on devices that cannot handle direct MKV/HEVC streams.

> 💙 **Acknowledgments**: Special thanks to [@EsTass](https://github.com/EsTass), the original author of phpmediaserver, for creating this amazing project.

---

## Playback Modes

PHPRouterMedia offers multiple playback modes to handle different device capabilities:

| Mode | Button Color | Description |
|------|-------------|-------------|
| **Play (Soft Decode)** | 🟢 Green | Original phpmediaserver playback. PHP serves the file and FFmpeg soft-decodes in real-time. **Drawback**: High CPU usage on the server, and video clarity may be reduced due to soft-decoding. Not recommended for low-power servers or high-bitrate files. |
| **HW (Hardware Decode)** | 🟡 Goldenrod | Uses hardware acceleration (VAAPI for AMD, NVENC for NVIDIA) to decode/encode. Much lower CPU usage. Requires HW transcoding support on the server. |
| **Remux (Stream Copy)** | 🟣 MediumSlateBlue | Copies video/audio streams without re-encoding (`-c copy`). **Zero CPU usage** for transcoding. Works when the client supports the original codec. Best for local network playback on capable devices. |
| **HLS (HTTP Live Streaming)** | 🔴 DeepPink | Outputs Apple-compatible HLS (`.m3u8` + `.ts` segments). **Best for iOS (iPad/iPhone Safari)** and other restricted clients that cannot handle raw MKV/HEVC streams. Streams efficiently with low overhead. |
| **Pre-cache (Remux Cache)** | ⚪ Grey | Background remux MKV→MP4. Once complete, a **Play Cached** button appears for zero-CPU direct file serving. Ideal for devices with weak decoding capabilities. |

> 💡 **Recommendation**: Use **HLS** for iOS devices, **Remux** for capable local devices, and **HW** if transcoding is truly necessary.

## Features

- **HTML5 Web Player** — PHP7 + SQLite + jQuery + FFmpeg, with audio/subtitle track selection
- **Remux Caching** — Background remux MKV/HEVC to MP4, enabling zero-CPU direct playback on weak devices
- **HLS Streaming** — Apple-compatible HLS output for iOS and restricted clients
- **Poster Wall** — Browse by genre, actor, year, or rating
- **Smart Groups** — Premiere, Continue Watching, Recommended, Recently Added
- **Real-time Transcoding** — FFmpeg real-time transcoding, no pre-encoding needed
- **Metadata Scraping** — OMDB, TheTVDB, IMDB support (API key required)
- **DLNA Server** — Built-in mini DLNA server, tested with VLC and Android
- **Multi-user** — Admin and player users
- **IP Control** — Country/IP whitelist/blacklist with auto-ban
- **Multi-language** — English / Spanish / Chinese

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 7+ |
| Database | SQLite |
| Frontend | jQuery 3.2.1 + Lazy Load |
| Media | FFmpeg / FFprobe |
| Streaming | DLNA (phpdlna), HLS |

## Requirements

### PHP Extensions (Required)
```
curl        # HTTP requests
iconv       # Character encoding conversion
pdo_sqlite  # SQLite database
sqlite3     # SQLite3 support
sockets     # Socket support
xmlrpc      # XML-RPC
zip         # ZIP extraction
soap        # DLNA server (optional)
```

### System Dependencies (Required)
```
ffmpeg >= 3.0
ffprobe
```

### System Dependencies (Recommended)
```
filebot       # Media file identification & renaming
wget          # Network download
pymediaident  # Python media identification tool
```

### PHP Configuration
Set `open_basedir` in `php.ini` to allow access to media directories:
```ini
open_basedir = ":/path/to/phproutermedia/:/path/to/your/media"
```

## Installation

### 1. Deploy Code
Copy project files to your web server directory, e.g., `/var/www/phproutermedia/`.

### 2. Configure Web Server

#### Apache Configuration (Port 8100)

Create `/etc/apache2/sites-available/phproutermedia.conf`:

```apache
# Listen on port 8100
Listen 8100

<VirtualHost *:8100>
    ServerName 0.0.0.0
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/phproutermedia

    <Directory /var/www/phproutermedia>
        Options FollowSymlinks
        AllowOverride All
        Require all granted
        # IMPORTANT: Allow PHP to access media directories
        # Add all paths where your media files are stored
        php_admin_value open_basedir "/var/www/phproutermedia:/path/to/your/media:/path/to/downloads"
    </Directory>

    DirectoryIndex index.php

    ErrorLog ${APACHE_LOG_DIR}/phproutermedia-error.log
    CustomLog ${APACHE_LOG_DIR}/phproutermedia-access.log combined
</VirtualHost>
```

Enable required Apache modules:
```bash
sudo a2enmod rewrite headers env
sudo a2ensite phproutermedia.conf
sudo systemctl restart apache2
```

> ⚠️ **Critical**: `open_basedir` must include all directories that PHP needs to access (media files, downloads, etc.). Without this, file browsing and playback will fail.

#### Nginx Example (Port 8100)
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

### 3. Set Permissions
```bash
chown -R www-data:www-data /var/www/phproutermedia/cache
chmod -R 755 /var/www/phproutermedia/cache
```

### 4. Configuration Files
- **`config.php`** — Basic configuration (media path, language, etc.)
- **`config.ws.php`** — Local overrides (API keys, etc.) — **not tracked by git**

### 5. Restart Web Server
```bash
# Apache
sudo systemctl restart apache2

# Nginx
sudo systemctl restart nginx
```

### 6. First Login
```
Username: admin
Password: admin01020304
```
**Change the password immediately after first login!**

## Default Port

The project defaults to port **8100**. Modify in your web server configuration.

## Project Structure

```
PHPRouterMedia/
├── actions/        # Backend APIs (play, scrape, DLNA, etc.)
├── cache/           # Cache directory (posters, subtitles, temp files)
├── config.php       # Main configuration file
├── config.ws.php    # Local overrides (not committed to git)
├── core/           # Core functions (scrapers, utilities)
├── dlna/           # DLNA server implementation
├── imgs/           # Image assets
├── js/             # JavaScript libraries
├── lang/           # Language files (en/es/zh)
├── index.php       # Entry page
└── README.md
```

## Original Project

This project is based on [phpmediaserver](https://github.com/EsTass/phpmediaserver) by [@EsTass](https://github.com/EsTass). We are grateful for the solid foundation and open-source contribution that made this fork possible.

## `.htaccess` — MIME Types (Critical for Playback)

The project includes an `.htaccess` file that sets essential MIME types for media playback. **Do not delete it.**

Key MIME types required for proper streaming:

```apache
# HLS streaming (iOS/Safari)
AddType application/x-mpegURL .m3u8
AddType video/MP2T .ts

# M(PEG)-DASH
AddType application/dash+xml .mpd

# Subtitles
AddType text/vtt .vtt
AddType text/srt .srt

# Video
AddType video/mp4 .mp4
AddType video/webm .webm
```

If you see playback failures, verify that:
1. Apache `AllowOverride All` is set (so `.htaccess` is read)
2. `mod_mime` is enabled (`a2enmod mime`)
3. The `.htaccess` file exists in the project root

## License

See the `LICENSE` file for details.
