# AliMPay 使用文档与项目介绍（也可参考原文档.md）

## 1. 项目介绍

AliMPay 是一个基于支付宝账单检测的聚合收款系统，免挂码支付，本项目配合docker镜像设计，支持自己diy设计后台或卡密平台，无需重建镜像。  
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

docker搭建速通：
- 首先上传本项目到对应文件，然后拉取镜像
docker run -d --name alipay -p 5002:80 -v "你的路径\AliMPay-main:/var/www/html" 241793/alipay:latest
- 填写支付宝配置(config/alipay.php)，上传自己的经营码或收款码到qrcode/business_qr.png
- 访问http://localhost:8080/health.php生成商户PID等
- 自己根据文末例子测试是否正常回调

---

## 2. 目录与数据说明

项目运行目录（容器内）：`/var/www/html`

关键目录：

- `config/`：配置文件（支付宝参数、PushPlus 配置）
- `data/`：订单与状态数据（SQLite、锁文件等）
- `logs/`：运行日志
- `qrcode/`：二维码资源,经营码改名：business_qr.png

---

## 3. Docker 部署（推荐）我已经打包好，可以跳过这个，项目没有放compose.yml文件

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
生成商户id
- `http://localhost:8080/health.php`（第一次必需访问这个）

## 3.2 使用 docker run


```bash
docker run -d --name alipay -p 5002:80 -v "你的路径\AliMPay-main:/var/www/html" 241793/alipay:latest
```

说明：

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

## 6. 创建订单时 notify_url,return_url 怎么填
文末有创建订单例子
POST /mapi.php  创建订单
payment_data = {
  "pid": PID,
  "type": "alipay",
  "out_trade_no": "ORDER_" + str(int(time.time())),#订单
  "notify_url": "",
  "return_url": "",
  "name": name,
  "money": money,
  "sign_type": "MD5",
}
`notify_url` 填你的业务系统回调地址，例如：

`https://your-domain.com/pay/callback`

如果暂时没有业务系统，可先准备一个简单接口，或者自定义通知，固定返回：

```text
success
```
`return_url` 填你的支付成功跳转的地址

---

## 7. 手动检测订单是否支付成功，适用于个人项目

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

## 11. 接入例子

```
import hashlib
import time
import webbrowser
from urllib.parse import urlencode,quote
import requests
BASE_URL = ""  # 你的 AliMPay 访问地址（按实际端口改）
PID = ""                # 商户ID（config/codepay.json）
MERCHANT_KEY = ""  # 商户密钥（config/codepay.json）

def generate_payment_sign(data: dict, merchant_key: str) -> str:
    """CodePay/AliMPay MD5 签名：过滤空值和 sign/sign_type，按键排序后 md5(str + key)"""
    sign_data = {
        k: v
        for k, v in data.items()
        if k not in ("sign", "sign_type") and v is not None and str(v) != ""
    }
    sign_str = "&".join(f"{k}={sign_data[k]}" for k in sorted(sign_data))
    return hashlib.md5((sign_str + merchant_key).encode("utf-8")).hexdigest()


def create_order(money: str = "0.01", name: str = "测试商品") -> dict:
    out_trade_no = f"DEMO{time.strftime('%Y%m%d%H%M%S')}"

    # 本地自测推荐：
    # notify_url 填 AliMPay 自己的 /notify.php
    # return_url 填一个你希望支付完成后跳转的页面
    payment_data = {
        "pid": PID,
        "type": "alipay",
        "out_trade_no": "ORDER_" + str(int(time.time())),
        "notify_url": f"1",
        "return_url": f"1",  # 同步跳转地址(用户支付完跳转的网页)
        "name": name,
        "money": money,
        "sign_type": "MD5",
    }
    payment_data["sign"] = generate_payment_sign(payment_data, MERCHANT_KEY)
    resp = requests.post(f"{BASE_URL}/mapi.php", data=payment_data, timeout=20)
    resp.raise_for_status()
    result = resp.json()

    return {
        "request": payment_data,
        "response": result,
    }


def open_submit_page(payment_data: dict) -> str:
    url = f"{BASE_URL}/submit.php?{urlencode(payment_data)}"
    webbrowser.open(url)
    return url



def poll_order(out_trade_no: str, interval: int = 3, max_retry: int = 40) -> None:
    print(f"开始轮询订单状态: {out_trade_no}")
    for i in range(1, max_retry + 1):

        try:

            r = requests.get(
                f"{BASE_URL}/api.php",
                params={"action": "order","pid":PID, "out_trade_no": out_trade_no},
                timeout=10,
            )
            data = r.json()
            print(data)
            status = data["status"]
            print(f"[{i}/{max_retry}] status={status} data={data}")
            if status == 1:
                print("订单已支付成功")
                return
        except Exception as e:
            print(f"[{i}/{max_retry}] 轮询异常: {e}")

        time.sleep(interval)

    print("轮询结束：订单仍未支付或未同步")


if __name__ == "__main__":

    ret = create_order(money="0.01", name="模拟测试订单1")
    req = ret["request"]
    res = ret["response"]

    print("下单请求:", req)
    print("下单响应:", res)

    if int(res.get("code", -1)) != 1:
        raise RuntimeError(f"下单失败: {res}")

    pay_page = open_submit_page(req)
    print("已打开支付页:", pay_page)
    poll_order(res["out_trade_no"])
```


