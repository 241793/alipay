# AliMPay 使用文档与项目介绍

## 1. 项目介绍

AliMPay 是一个基于支付宝账单检测的聚合收款系统，兼容 CodePay 风格接口。  
系统核心能力：

- 创建支付订单（生成支付页/二维码）
- 轮询支付宝账单并匹配订单
- 支付成功后自动回调商户 `notify_url`
- 支持 PushPlus 消息通知
- 提供订单状态查询接口

适用场景：

- 个人站点收款
- 简单订单支付状态管理
- 需要“支付成功后自动通知”的系统对接

---

## 2. 目录与数据说明

项目运行目录（容器内）：`/var/www/html`

关键目录：

- `config/`：配置文件（支付宝参数、PushPlus 配置）
- `data/`：订单与状态数据（SQLite、锁文件等）
- `logs/`：运行日志
- `qrcode/`：二维码资源

建议：生产环境务必持久化整个项目目录或至少持久化 `data/ logs/ qrcode/ config/`。

---

## 3. Docker 部署（推荐）

## 3.1 使用 docker compose

在项目根目录执行：

```bash
docker compose down
docker compose up -d --build
```

默认映射：

- 本地项目根目录 -> 容器 `/var/www/html`
- 端口：`8080 -> 80`

访问地址：

- `http://localhost:8080/health.php`

## 3.2 使用 docker run

```bash
docker build -t alimpay:latest .
docker rm -f alimpay
docker run -d --name alimpay -p 8080:80 -v "D:\Download\AliMPay-main:/var/www/html" alimpay:latest
```

说明：

- 容器启动时会自动尝试修复目录权限
- 首次启动若无 `vendor/`，会自动执行 Composer 安装（使用国内镜像）

---

## 4. 配置说明

## 4.1 基础配置

首次启动后，确认文件：

- `config/alipay.php`（若不存在会自动从示例复制）

必填项（按你的支付宝应用信息填写）：

- `app_id`
- `private_key`
- `alipay_public_key`
- `transfer_user_id`

## 4.2 PushPlus 配置（支付成功推送）

在 `config/alipay.php` 添加或修改：

```php
'pushplus' => [
    'enabled' => true,
    'token' => '你的pushplus_token',
    'template' => 'markdown',
    'channel' => '',
    'topic' => '',
    'title_prefix' => 'AliMPay',
    'endpoint' => 'http://www.pushplus.plus/send',
],
```

说明：

- `enabled=true` 才会发送
- PushPlus 与商户回调互不冲突，会并行执行

---

## 5. 回调与通知机制

支付成功后，系统会尝试两类通知：

1. 商户回调：调用订单中的 `notify_url`
2. PushPlus 推送：调用 PushPlus API 发送消息

注意：

- `notify_url` 仍建议填写你的业务回调地址（返回 `success`）
- PushPlus 不替代业务回调，只是额外通知通道

---

## 6. 创建订单时 notify_url 怎么填

`notify_url` 填你的业务系统回调地址，例如：

`https://your-domain.com/pay/callback`

如果暂时没有业务系统，可先准备一个简单接口，固定返回：

```text
success
```

---

## 7. 手动检测订单是否支付成功

接口：

`GET /api.php?action=order`

参数：

- `pid`：商户 ID（必填）
- `out_trade_no`：订单号（必填）
- `key`：商户密钥（可选）

示例：

```bash
curl "http://localhost:8080/api.php?action=order&pid=1001xxxx&out_trade_no=ORDER_123"
```

返回判断：

- `code=1 且 status=1`：已支付
- `code=1 且 status=0`：未支付
- `code=-1`：查询失败

---

## 8. 健康检查

接口：

`GET /health.php`

示例：

```bash
curl "http://localhost:8080/health.php"
```

可用于检查：

- 数据库状态
- 监控状态
- 支付相关服务状态

---

## 9. 常见问题排查

### 9.1 `health.php` 报 `Permission denied`

原因：挂载目录权限不足，Apache/PHP 无法读取项目文件。  
处理：

- 确认挂载命令正确：`-v "本地项目目录:/var/www/html"`
- 使用当前项目提供的 entrypoint 自动修复权限
- 必要时重建容器：

```bash
docker compose down
docker compose up -d --build
```

### 9.2 Composer 安装超时

项目已默认设置：

- 国内镜像源
- 进程超时 `1800`
- 内存限制 `-1`

如仍慢，可重试一次构建或检查本机网络代理。

### 9.3 通知成功但页面未及时变“已支付”

当前版本已做两项修复：

- 前端轮询请求禁用缓存
- 订单查询会优先返回已支付记录，并带实时补偿检测

若仍异常，优先检查是否重复创建了同一 `out_trade_no` 订单。

---

## 10. 建议上线清单

- 使用独立域名并配置 HTTPS
- 妥善保存 `config/alipay.php` 与商户密钥
- 打开 `pushplus` 便于实时告警
- 定期备份 `data/`（特别是 `codepay.db`）

