<?php

return [
    // 支付宝网关地址
    'server_url' => 'https://openapi.alipay.com',
    
    // 应用ID - 请填写您的AppId，例如：2019091767145019
    'app_id' => '',
    
    // 应用私钥 - 请填写您的应用私钥，例如：MIIEvQIBADANB...
    'private_key' => '',
    
    // 支付宝公钥 - 请填写您的支付宝公钥，例如：MIIBIjANBg...
    'alipay_public_key' => '',
    
    // 转账用户ID - 请填写您的支付宝用户ID，用于转帐方式支付
    'transfer_user_id' => '',
    
    // 签名方式
    'sign_type' => 'RSA2',
    
    // 编码格式
    'charset' => 'UTF-8',
    
    // 返回格式
    'format' => 'json',
    
    // 日志配置
    'log' => [
        'file' => __DIR__ . '/../logs/alipay.log',
        'level' => 'info',
        'type' => 'single',
        'max_file' => 30,
    ],
    
    // 账务流水查询配置
    'bill_query' => [
        'default_page_size' => 2000,
        'max_page_size' => 2000,
        'date_format' => 'Y-m-d H:i:s',
    ],
    
    // 码支付配置
    'payment' => [
        'max_wait_time' => 300,      // 最大等待时间（秒）- 5分钟
        'check_interval' => 3,       // 检查间隔（秒）
        'query_minutes_back' => 30,  // 查询历史记录的时间范围（分钟）
        'order_timeout' => 300,      // 订单超时时间（秒）- 5分钟后自动删除
        'auto_cleanup' => true,      // 是否启用自动清理过期订单
        'qr_code_size' => 300,       // 二维码尺寸
        'qr_code_margin' => 10,      // 二维码边距
        
        // 经营码收款配置
        'business_qr_mode' => [
            'enabled' => true,      // 是否启用经营码收款模式
            'qr_code_path' => __DIR__ . '/../qrcode/business_qr.png',  // 经营码二维码路径
            'amount_offset' => 0.01, // 金额偏移量，用于区分相同金额的订单
            'match_tolerance' => 300, // 订单匹配时间容差（秒）- 账单时间必须在订单创建后5分钟内
            'payment_timeout' => 300, // 客户付款超时时间（秒）- 客户可以在5分钟内完成付款
            'description' => '经营码收款模式：不依赖备注单号匹配，通过金额+时间匹配订单。相同金额时自动增加0.01元偏移量。'
        ],
        
        // 防风控URL配置
        'anti_risk_url' => [
            'enabled' => true,       // 是否启用防风控URL
            'outer_app_id' => '20000218',  // 外层应用ID
            'inner_app_id' => '20000116',  // 内层应用ID
            'base_urls' => [
                'mdeduct_landing' => 'https://render.alipay.com/p/c/mdeduct-landing',
                'render_scheme' => 'https://render.alipay.com/p/s/i'
            ]
        ]
    ],

    // PushPlus notifications
    'pushplus' => [
        'enabled' => false,
        'token' => '',
        'template' => 'markdown',
        'channel' => '',
        'topic' => '',
        'title_prefix' => 'AliMPay',
        'endpoint' => 'http://www.pushplus.plus/send',
    ],
];
