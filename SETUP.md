# 环境搭建手册（SETUP）

> 本文档记录本项目从零搭建开发环境的完整命令，供复现与交接使用。
> 操作系统：Windows 11 专业版（AMD64）
> 项目路径：`H:\PHP\inventory-adjustment`

---

## 0. 环境探查（确认机器上有什么）

```powershell
# 查看 PHP 是否存在
php -v

# 查看 Composer 是否存在
composer -V

# 查看磁盘有哪些
Get-PSDrive -PSProvider FileSystem

# 查看 H:\PHP 目录是否为空
Get-ChildItem "H:\PHP" -Force
```

---

## 1. 安装 PHP 8.3

```powershell
winget install PHP.PHP.8.3 --accept-source-agreements --accept-package-agreements --silent
```

- `winget` 是 Windows 11 自带的包管理器
- `PHP.PHP.8.3` 是 winget 仓库里的 PHP 8.3 包名
- `--accept-*` 自动同意协议，不弹确认框
- `--silent` 静默安装

实际安装位置：
```
C:\Users\banying\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe
```

---

## 2. 配置 php.ini（启用必要扩展）

### 2.1 复制配置模板

```powershell
$phpDir = "C:\Users\banying\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe"
Copy-Item "$phpDir\php.ini-development" "$phpDir\php.ini" -Force
```

PHP 自带两份模板：`php.ini-development`（开发用，报错全开）和 `php.ini-production`（生产用）。复制为 `php.ini` 才会被加载。

### 2.2 启用扩展

```powershell
$phpIni = "$phpDir\php.ini"
$content = Get-Content $phpIni -Raw

# 告诉 PHP 扩展 DLL 在哪
$content = $content -replace ';extension_dir = "ext"', 'extension_dir = "ext"'

# 逐个取消注释需要的扩展
$exts = @('curl','fileinfo','gd','intl','mbstring','openssl','pdo_sqlite','sodium','sqlite3','zip')
foreach ($e in $exts) {
    $content = $content -replace ";extension=$e", "extension=$e"
}

Set-Content -Path $phpIni -Value $content -Encoding UTF8 -NoNewline
```

需要这些扩展的原因：

| 扩展 | 用途 |
|---|---|
| `mbstring` | 处理多字节字符（中文），Laravel 硬依赖 |
| `openssl` | 加密、APP_KEY、HTTPS |
| `pdo_sqlite` + `sqlite3` | 操作 SQLite 数据库 |
| `fileinfo` | 上传文件类型检测 |
| `curl` | HTTP 请求（Guzzle 用） |
| `zip` | 解压/打包（Composer 用） |
| `gd` / `intl` / `sodium` | 备用，Laravel 生态常用 |

### 2.3 验证扩展加载

```powershell
& $phpDir\php.exe -m
& $phpDir\php.exe -r "var_dump(PDO::getAvailableDrivers());"
```

---

## 3. 安装 Composer（便携版）

winget 装 Composer 会失败（安装器需要 UAC 提权），改用官方便携版：

```powershell
# 建一个放工具的目录
New-Item -ItemType Directory -Force -Path "H:\PHP\bin"

# 直接下载 composer.phar
Invoke-WebRequest -Uri "https://getcomposer.org/composer.phar" -OutFile "H:\PHP\bin\composer.phar" -UseBasicParsing

# 验证
php "H:\PHP\bin\composer.phar" --version
```

`composer.phar` 就是 Composer 本体，不需要安装，调用方式：
```powershell
php H:\PHP\bin\composer.phar <命令>
```

---

## 4. 创建 Laravel 项目

```powershell
cd H:\PHP
php H:\PHP\bin\composer.phar create-project laravel/laravel inventory-adjustment --no-interaction
```

这条命令会自动完成：
- 下载框架骨架
- 安装依赖包
- 生成 `APP_KEY`
- 创建 `database/database.sqlite`
- 跑默认迁移

实际版本：**Laravel 13.32.0**（2026 年当前稳定版）

---

## 5. 配置 .env

```powershell
cd H:\PHP\inventory-adjustment

# 查看数据库配置
Select-String -Path ".env" -Pattern "^DB_|^APP_NAME"
```

Laravel 13 默认就是 SQLite 配置，无需改数据库连接。

修改应用名（可选）：
```powershell
(Get-Content .env) -replace 'APP_NAME=Laravel', 'APP_NAME=InventoryAdjustment' | Set-Content .env -Encoding UTF8
```

---

## 6. 安装 Git

```powershell
winget install Git.Git --accept-source-agreements --accept-package-agreements
```

- `Git.Git` 是 winget 里 Git for Windows 的包名
- 装完后 `git.exe` 在 `C:\Program Files\Git\cmd\git.exe`
- 新装的 Git 在**已打开的终端里 PATH 不生效**，要新开终端

---

## 7. Git 初始化与首次提交

```powershell
cd H:\PHP\inventory-adjustment
$git = "C:\Program Files\Git\cmd\git.exe"

& $git init
& $git config user.email "dev@local.test"
& $git config user.name "Dev"
& $git add .
& $git commit -m "init: fresh Laravel 13 install with SQLite configured"
& $git branch -M main
```

推到 GitHub 前改成自己的身份：
```powershell
& $git config user.name "你的名字"
& $git config user.email "你的邮箱@example.com"
```

---

## 8. 冒烟测试

```powershell
cd H:\PHP\inventory-adjustment
$php = "C:\Users\banying\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"

# 后台启动开发服务器
$proc = Start-Process -FilePath $php -ArgumentList "artisan","serve","--host=127.0.0.1","--port=8000" -PassThru -WindowStyle Hidden
Start-Sleep -Seconds 4

# 请求首页
Invoke-WebRequest -Uri "http://127.0.0.1:8000" -UseBasicParsing

# 关闭服务器
Stop-Process -Id $proc.Id -Force
```

返回 `HTTP 200` 即表示环境正常。

---

## 日常使用速查

```powershell
# PHP
$php = "C:\Users\banying\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"

# Composer
$composer = "H:\PHP\bin\composer.phar"

# Git
$git = "C:\Program Files\Git\cmd\git.exe"

# 项目目录
cd H:\PHP\inventory-adjustment

# 常用命令
& $php artisan migrate
& $php $composer require <包名>
& $git status
```
