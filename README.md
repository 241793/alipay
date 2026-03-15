# AliMPay - 支付宝码支付系统

一个基于支付宝转账码的自动化支付解决方案，支持经营码收款和转账码收款两种模式。

![751192321ae102a907612a9ae6307903.png](https://i.mji.rip/2025/07/15/751192321ae102a907612a9ae6307903.png)

## 特性

- 🚀 **自动监控**: 实时监控支付宝账单，自动检测支付状态
- 📱 **码支付**: 支持经营码收款和转账码收款
- ⏰ **智能超时**: 5分钟支付时限，超时订单自动清理
- 🔐 **安全可靠**: MD5签名验证，防止数据篡改
- 🎯 **协议兼容**: 100%兼容CodePay协议

## 快速配置

### 1. 环境要求

- PHP 7.4+
- Composer
- 支付宝开放平台应用

### 2. 安装步骤

```bash
# 下载项目
# 安装依赖
composer install

# 复制配置文件
cp config/alipay.example.php config/alipay.php
```

### 3. 支付宝配置

#### 获取支付宝应用参数

1. 登录 [支付宝开放平台](https://open.alipay.com)
2. 创建"网页/移动应用"
3. 获取以下参数：
   - **应用ID**: 应用详情页的AppId
   - **应用私钥**: 使用密钥工具生成
   - **支付宝公钥**: 从平台获取
   - **用户ID**: 账户中心的账号ID

可以参考这个[文章](https://www.mazhifu.me/mpay/35.html)申请应用，一般都会有一个默认的 生成密钥即可


#### 配置文件设置

编辑 `config/alipay.php`：

```php
<?php
return [
    'app_id' => 'YOUR_APP_ID',                    // 应用ID
    'private_key' => 'YOUR_PRIVATE_KEY',          // 应用私钥
    'alipay_public_key' => 'YOUR_ALIPAY_PUBLIC_KEY', // 支付宝公钥
    'transfer_user_id' => 'YOUR_USER_ID',         // 支付宝用户ID
    
    // 其他配置保持默认即可
];
```

#### 获取商户密钥

首次运行需要获取系统分配的商户ID和密钥：

```bash
# 启动服务
# 访问健康检查，系统会自动生成商户配置
curl http://domain/health.php

# 查看生成的商户信息
cat config/codepay.json
```

**商户配置文件示例**：
```json
{
    "merchant_id": "1001123456789012",
    "merchant_key": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
    "created_at": "2024-01-01 12:00:00",
    "status": 1,
    "balance": "0.00",
    "rate": "96"
}
```

**重要**：请妥善保存 `merchant_id` (商户ID) 和 `merchant_key` （商户密钥） ，这是商户接入时必需的参数。

### 4. 启动服务 下方两个模式二选一


## 码支付模式

### 经营码收款（推荐）

> 目前支付宝放水，进入经营码申请页面填写商户信息时，返回，就会提示是否免填写开启经营码
> 如果实在没有此处使用收款码替代，或者使用下方转账方式

**特点**: 无需转账备注，通过金额+时间匹配订单

1. **上传经营码**：将支付宝经营码二维码保存为 `qrcode/business_qr.png`

2. **启用配置**：编辑 `config/alipay.php`
```php
'payment' => [
    'business_qr_mode' => [
        'enabled' => true,  // 启用经营码模式
    ]
]
```

**工作原理**：
- 相同金额的订单自动增加0.01元偏移（1.00→1.01→1.02...）
- 客户扫码支付对应金额
- 系统通过金额和时间匹配订单

### 转账收款（无需额外配置）

**工作原理**：
- 客户转账时填写订单号作为备注
- 系统监控账单，通过备注匹配订单

## 支付宝调用流程

### 创建支付订单

```php
// 发起支付请求
$params = [
    'pid' => '商户ID',
    'type' => 'alipay',
    'out_trade_no' => '订单号',
    'notify_url' => '通知地址',
    'return_url' => '返回地址', 
    'name' => '商品名称',
    'money' => '支付金额',
    'sign' => '签名'
];

// POST 到 /submit.php 或 /mapi.php
```

### 监控支付状态

系统会自动：

1. **查询账单**: 每30秒查询支付宝账单API
2. **匹配订单**: 根据模式匹配相应订单
3. **更新状态**: 自动更新订单为已支付
4. **发送通知**: 向商户notify_url发送支付成功通知

### 查询订单状态

```bash
# 查询单个订单
GET /api.php?act=order&pid=商户ID&out_trade_no=订单号

# 查询商户信息
GET /api.php?act=query&pid=商户ID&key=商户密钥
```

## 支付页面

访问 `/submit.php` 生成支付页面，包含：

- 订单信息展示
- 二维码显示
- 实时倒计时（5分钟）
- 支付状态检查

## 系统监控

### 健康检查

```bash
# 检查系统状态
curl http://domain/health.php
```

### 日志查看

```bash
# 查看实时日志
tail -f data/app.log
```

## 目录结构

```
alimpay/
├── api.php              # API接口
├── submit.php           # 支付页面
├── mapi.php            # 移动端API  
├── health.php          # 健康检查
├── qrcode.php          # 二维码访问
├── config/             # 配置文件
│   └── alipay.php     # 支付宝配置
├── src/Core/          # 核心类库
├── data/              # 数据存储
└── qrcode/            # 二维码文件
```

## 签名算法

使用MD5签名算法：

```php
// 1. 参数按键名升序排序
// 2. 拼接成 key1=value1&key2=value2 格式  
// 3. 末尾拼接商户密钥
// 4. 计算MD5值

$signStr = 'money=0.01&name=测试&out_trade_no=123&pid=1001';
$sign = md5($signStr . $merchantKey);
```

## 常见问题


### Q: 支付检测延迟多久？  
A: 通常在支付完成后30秒内检测到并发送通知。

### Q: 订单超时时间是多久？
A: 订单创建后5分钟内必须完成支付，超时自动删除。

### Q: 如何调试支付问题？
A: 查看 `data/app.log` 日志文件，使用健康检查接口排查。

### Q: 如何部署到生产环境？
A: 将项目部署到Web服务器，配置好支付宝参数即可。


## 开源协议

MIT License

## 免责声明

本项目仅供学习交流使用，使用者需确保遵守相关法律法规和支付宝服务协议。 
