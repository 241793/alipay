#B-Bot

本项目仅提供学习和参考,请勿违法操作,请于24小时内删除！
2026/3/3 1.0.2 优化授权逻辑，新增更新指令，优化后台页面，主题切换，手机端适配
2026/2/28 1.0.1 新增内置青龙，可告别青龙面板运行脚本
1.0.0 新增适配器

win版已停更(0.0.9)
配套安卓APP1.0.3：https://github.com/241793/B-Bot/releases/download/1.0.9/b-bot1.0.4.apk

一个可以通过AI驱动的机器人框架,通过python实现,具有多协议接入、插件化架构、规则引擎、持久化存储和可视化面板的自动化工具，最新版实现Agent管理，向openclaw靠齐。
win电脑需要有python环境.UI有点丑懒得优化了，但功能俱全。
<img width="1821" height="895" alt="1774886266404" src="https://github.com/user-attachments/assets/b38a93e0-6389-46bc-a105-d2b1462ad1fd" />

ntqq的llonebot插件配置ws:ws://127.0.0.1:port/ws/qq

对接qq教程：<a href="https://bchome.dpdns.org/index.php/archives/157/" target="_blank">llonebot(win/docker)</a>

2026/3/26 docker镜像重构，1.0.6版本及以上旧命令已无法使用。首次启动会自动构建需要的文件，如果之前用过或已有数据，请自己备份data文件夹，避免可能被覆盖，启动后在把备份的覆盖进去
新.
1.0.6版本新增命令：更新、回退、重启等，新增AI大脑功能，加入养虾，BbotClaw
【首次部署请发更新命令，更新最新内置数据】
说明：5000是web端口，8888是ws的端口
```docker
docker run -d \
  --name bbot \
  --restart unless-stopped \
  -p 5000:5000 \
  -p 8888:8888 \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v 你的docker文件夹地址\data:/app/mount \
  241793/b-bot:latest
```

旧
docker教程：<a href="https://bchome.dpdns.org/index.php/archives/168/" target="_blank">docker</a>

```docker
docker run -d \
  --name bbot \
  --restart unless-stopped \
  -p 5000:5000 \
  -p 8888:8888 \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v 你的docker文件夹地址\data:/app/data \
  -v 你的docker文件夹地址\plugins:/app/plugins \
  241793/b-bot:latest
```
## 功能特性

- **多协议接入器**: 支持WebSocket等协议，可对接QQ等平台
- **插件化架构**: 支持Python插件的动态加载、卸载和管理
- **规则引擎**: 基于正则表达式、关键词的消息匹配和处理
- **持久化存储**: 支持数据桶存储机制
- **中间件系统**: 提供统一的消息处理接口
- **可视化面板**: 完整的Web管理界面

## 快速开始

### 1. 启动框架

```bash
B-BOT.exe一键运行
```

### 2. 访问Web管理界面

打开浏览器访问 `http://127.0.0.1:5000`

### 3. WebSocket连接
.env文件可以更改端口
客户端可以连接到 `ws://127.0.0.1:8888` 发送和接收消息
ntqq的隆内博特插件配置ws:ws://127.0.0.1:port/ws/qq

### 适配器管理（支持对接渠道：QQ、wxclawbot、tgbot等更多渠道正在对接中）
#### Custom 适配器对接文档，对接外部
##### 1. 功能说明
- `custom` 适配器用于把外部系统（客服系统、工单系统、业务后台）接入 B-BOT。
- 支持两条链路：
1. 入站：外部系统调用 webhook，把消息推送给机器人处理。
2. 出站：机器人回复后，回调到你的业务系统。

##### 2. Web 端配置
进入 `适配器 -> custom -> 查看/编辑`，配置以下字段：

- `启用入站 Token 校验`：建议开启。
- `入站 Token`：外部系统调用 webhook 时用于鉴权。
- `默认 user_id`：外部未传 `user_id` 时使用。
- `默认发送者名称`：外部未传昵称时使用。
- `启用消息回调`：是否把机器人回复回传给业务系统。
- `回调 URL`：业务系统接收机器人回复的 HTTP 地址。
- `回调 Bearer Token`：回调时附带 `Authorization: Bearer xxx`。
- `附加 Headers(JSON)`：额外请求头，例 `{"X-Source":"bbot"}`。
- `启用回调验签头`：开启后会附带 `X-BBot-Signature`。
- `验签密钥（Sign Secret）`：用于生成回调签名。

同时支持 `Web 一键联调`：
- 在 custom 配置页可直接填写测试 JSON，点击“**一键联调入站**”调试。

##### 3. 入站接口
URL：

```text
POST /api/adapters/custom/inbound
Content-Type: application/json
```

鉴权（二选一）：

```text
X-Custom-Token: <你的token>
```

或

```text
Authorization: Bearer <你的token>
```

请求体示例：

```json
{
  "message_id": "cs_10001",
  "user_id": "u_9527",
  "group_id": "",
  "sender": {"nickname": "张三"},
  "content": "帮我查一下订单A1001状态",
  "source": "crm",
  "timestamp": 1775600000
}
```

成功响应：

```json
{
  "success": true,
  "message": "消息已进入处理队列",
  "data": {
    "message_id": "cs_10001",
    "user_id": "u_9527",
    "group_id": ""
  }
}
```

##### 4. 出站回调格式（机器人回复 -> 业务系统）
当启用 `消息回调` 后，B-BOT 会 POST 到你配置的回调 URL：

```json
{
  "event": "bot_reply",
  "adapter": "custom",
  "target_type": "user",
  "target_id": "u_9527",
  "content": "你好，订单 A1001 已发货，预计明天送达。",
  "timestamp": 1775600123,
  "meta": {
    "content": "你好，订单 A1001 已发货，预计明天送达。"
  }
}
```

如果开启了回调验签，会带上这些 Header：

```text
X-BBot-Signature: sha256=<hex>
X-BBot-Sign-Alg: hmac-sha256
X-BBot-Timestamp: <unix_ts>
```

签名计算方式：

```text
HMAC_SHA256(sign_secret, payload_json_sorted)
```

说明：
- `payload_json_sorted` 为 JSON 序列化后的字符串，键按字典序排序（`sort_keys=True`）。
- 当前实现不依赖请求体原始空白字符，按上述规则重建 JSON 后计算即可。

建议你的回调接口返回 HTTP 2xx，且可选返回：

```json
{
  "success": true,
  "data": {"message_id": "biz_reply_123"}
}
```

##### 5. 知识库隔离（custom 专用）
- AI 配置新增了 `Custom 适配器知识桶`，默认值：`ai_knowledge_custom`。
- 当消息来源平台是 `custom` 时，AI 会优先读取 `custom` 专用知识桶，避免与主知识库 `ai_knowledge` 冲突。
- 你可以在 AI 知识库页面切换/录入 `ai_knowledge_custom` 作为外部渠道专用知识数据。

##### 6. 外部系统调用示例
###### 6.1 Python（入站调用）
```python
import requests

url = "http://127.0.0.1:5000/api/adapters/custom/inbound"
headers = {
    "Content-Type": "application/json",
    "X-Custom-Token": "YOUR_INBOUND_TOKEN",
}
payload = {
    "message_id": "crm_1001",
    "user_id": "u_1001",
    "content": "帮我查询订单状态",
    "sender": {"nickname": "客服系统用户"},
    "source": "crm",
}
r = requests.post(url, json=payload, headers=headers, timeout=10)
print(r.status_code, r.text)
```

###### 6.2 Node.js（入站调用）
```javascript
const url = "http://127.0.0.1:5000/api/adapters/custom/inbound";
const payload = {
  message_id: "crm_1002",
  user_id: "u_1002",
  content: "帮我看一下退款进度",
  source: "crm"
};

fetch(url, {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "X-Custom-Token": "YOUR_INBOUND_TOKEN"
  },
  body: JSON.stringify(payload)
}).then(async (resp) => {
  console.log(resp.status, await resp.text());
});
```

###### 6.3 Java（入站调用，JDK11 HttpClient）
```java
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;

public class CustomInboundDemo {
    public static void main(String[] args) throws Exception {
        String body = "{\"message_id\":\"crm_1003\",\"user_id\":\"u_1003\",\"content\":\"测试Java入站\"}";
        HttpRequest request = HttpRequest.newBuilder()
                .uri(URI.create("http://127.0.0.1:5000/api/adapters/custom/inbound"))
                .header("Content-Type", "application/json")
                .header("X-Custom-Token", "YOUR_INBOUND_TOKEN")
                .POST(HttpRequest.BodyPublishers.ofString(body))
                .build();
        HttpClient client = HttpClient.newHttpClient();
        HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());
        System.out.println(response.statusCode() + " " + response.body());
    }
}
```

##### 7. 回调验签示例
###### 7.1 Python（校验 X-BBot-Signature）
```python
import hmac
import hashlib
import json

def verify_signature(payload_dict, sign_secret, header_signature):
    payload_json = json.dumps(payload_dict, ensure_ascii=False, separators=(",", ":"), sort_keys=True)
    expected = "sha256=" + hmac.new(
        sign_secret.encode("utf-8"),
        payload_json.encode("utf-8"),
        hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, header_signature or "")
```

###### 7.2 Node.js（校验 X-BBot-Signature）
```javascript
import crypto from "crypto";
import stringify from "json-stable-stringify";

function verifySignature(payload, secret, headerSig) {
  const payloadJson = stringify(payload); // 稳定排序序列化
  const expected = "sha256=" + crypto.createHmac("sha256", secret).update(payloadJson, "utf8").digest("hex");
  return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(headerSig || ""));
}
```

###### 7.3 Java（校验 X-BBot-Signature）
```java
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;

public class SignVerify {
    public static String hmacSha256Hex(String secret, String payloadJsonSorted) throws Exception {
        Mac mac = Mac.getInstance("HmacSHA256");
        mac.init(new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
        byte[] out = mac.doFinal(payloadJsonSorted.getBytes(StandardCharsets.UTF_8));
        StringBuilder sb = new StringBuilder();
        for (byte b : out) sb.append(String.format("%02x", b));
        return "sha256=" + sb;
    }
}
```


### 规则管理
- 查看系统规则
- 添加新规则（支持正则表达式、关键词、完全匹配）

### 数据桶管理

### 日志管理

## WebSocket协议

### 消息格式

发送消息格式：
```json
{
  "id": "消息唯一ID",
  "type": "message",
  "content": "消息内容",
  "user_id": "用户ID",
  "group_id": "群ID",
  "timestamp": "时间戳",
  "raw_message": "原始消息"
}
```

接收消息格式：
```json
{
  "content": "回复内容",
  "to_user_id": "目标用户ID"
}
```

## 插件开发

### 开发规范
- 中间遵循异步运行，插件调用时需使用await异步操作
- 所有插件必须遵循插件开发规范
- 插件文件名应使用下划线命名法
- 规则名称应具有唯一性
- 代码应包含适当的错误处理
#### 基本插件结构
- 一些参数：platform: qq(ws对接的渠道)、web_ui(web端)

##### PYTHON插件编写方法一
```python
"""
插件名称
插件描述
"""

__description__ = "插件描述"
__version__ = "1.0.0"
__author__ = "开发者"
__imType__="渠道，例如qq"
__admin__=False#是否仅管理员
#配参
__param__ = {"required":True,"key":"桶名.key","bool":False,"placeholder":"","name":"输入框的名字","desc":"介绍"}
import asyncio
async def handle_message(msg, middleware):
    """
    处理消息的函数
    """
    content = msg["content"]#消息内容{}
    user_id = msg["user_id"]
    platform = msg["platform"]
    #这种为推送消息的方式，连续交互时使用，需要填写多种参数
    await middleware.send_message(platform, user_id, "要发送的消息",msg)
    #这种为直接回复消息，适合结束的地方使用，只支持rules里面绑定的函数使用（例如：handle_message）
    return {
        "content": "回复内容",
        "to_user_id": user_id#可选
    }

# 插件规则
rules = [
    {
        "name": "规则名称",
        "pattern": r"匹配模式",
        "handler": handle_message,
        "rule_type": "regex",  # regex, keyword, fullmatch,匹配类型
        "priority": 1,
        "description": "规则描述"
    }
]

```
##### 插件编写方法二
```python
"""
插件名称
插件描述
"""

__description__ = "插件描述"
__version__ = "1.0.0"
__author__ = "开发者"
#配参
__param__ = {"required":True,"key":"桶名.key","bool":False,"placeholder":"","name":"输入框的名字","desc":"介绍"}
import asyncio,re
async def handle_message(msg, middleware):
    #相当于全局监听框架信息，需要自己写匹配规则
    content = msg["content"]
    if content:
       if re.match("^你好$",content):
          return {"content": "你也好"}

def register(middleware):
    """
    当框架加载此插件时，会调用这个函数。
    """
    # 通过中间件注册你的消息处理器
    middleware.register_message_handler(handle_message)
    print(f"示例插件 '{__description__}' 已加载并注册了消息处理器。")

```
## 配置

win框架支持以下环境变量配置：

- `qq_HOST`: 反向WebSocket服务器主机，默认 `0.0.0.0`
- `qq_PORT`: 反向WebSocket服务器端口，默认 `8888/ws/qq` (用于发送回复给QQ等平台)
- `WEB_UI_HOST`: Web界面主机，默认 `0.0.0.0`
- `WEB_UI_PORT`: Web界面端口，默认 `5000`

## 特殊说明

1. **插件热加载**: 支持动态启用/禁用、在线编辑和实时保存
2. **WebSocket服务器**: 提供WebSocket服务供客户端连接
3. **规则优先级**: 数值越大优先级越高
4. **日志轮转**: 自动管理日志文件大小和数量
5. **QQ集成**: 支持与QQ平台集成，通过双WebSocket架构实现消息收发
   - 一些功能: 自动同意好友请求、自动撤回、群管、点赞
6. **反向WebSocket**: 用于将处理结果发送回消息平台
7. **外部容器对接青龙面板**: 支持青龙面板对接，规则运行，插件异步调用内置青龙函数

---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
# 插件开发指南

本文档旨在帮助开发者了解如何在 `B-BOT` 中创建和开发插件。插件是扩展机器人功能的核心。

## 1. 插件基础
插件本质上是一个遵循特定规范的 Python 模块，框架会自动加载并运行它。

- **位置**: 所有插件都应放置在 `plugins/` 目录下。
- **入口**: 框架会寻找并执行插件模块中的 `register` 函数来初始化插件。

### 关于计划任务怎么判断是否内部命令（两种方式）,搭配计划任务功能食用
1、message.get("internal_source") = True(内部来源)
2、message.get("platform") = internal(内部来源)
### 图片和视频（CQ码）
image: 类型base64数据,url
video,record: url
[CQ:image,file=base64://{image}]/[CQ:image,file={image}]
video
[CQ:video,file={video}]
record
[CQ:record,file={record}]
例如: await middleware.send_message(message.get("platform"),target_id,f"{[CQ:video,file={video}]}",message)

一个最简单的插件结构如下：

```python
# /plugins/my_awesome_plugin.py
__description__ = "插件描述"
__version__ = "1.0.0"
__author__ = "开发者"
__imType__="qq"#插件仅某渠道可用
__admin__=False#是否仅管理员可用
#配参：可选
__param__ = {"required":True,"key":"桶名.key","bool":False,"placeholder":"","name":"输入框的名字","desc":"介绍"}

# 插件的主体功能
async def my_message_handler(message: dict):
    """
    这是一个消息处理器，它会接收所有通过框架的消息。
    """
    content = message.get("content", "").strip()
    if content == "你好":
        # 当收到“你好”时，返回一个响应字典
        return {"content": "你好！我是你的机器人助手。"}
    # 如果不处理此消息，则不返回任何内容
    return None

# 插件注册函数（必需）
def register(middleware):
    """
    当框架加载此插件时，会调用这个函数。
    """
    # 通过中间件注册你的消息处理器
    middleware.register_message_handler(my_message_handler)
    middleware.logger.info("示例插件 'my_awesome_plugin' 已加载并注册了消息处理器。")#日志打印,print看不见

```

## 2. 如何使用 Middleware 功能

`Middleware` 对象是插件与框架交互的唯一桥梁。当框架调用你的 `register` 函数时，会将一个 `Middleware` 实例作为参数传递给你。你应该将其保存下来，以便在插件的其他地方使用。

虽然在上面的简单示例中我们只在 `register` 函数里用了一次 `middleware`，但更复杂的插件可能需要在多个地方调用它。你可以将它保存在一个类或者全局变量中。

### 2.1 接收和响应消息

最常见的插件功能是响应用户的消息。

- **注册处理器**: 使用 `middleware.register_message_handler(your_handler_function)` 来监听所有消息。
- **处理消息**: 你的处理器函数会收到一个 `message` 字典，它包含了消息的所有信息（如内容、发送者ID、群组ID等）。
- **快速响应**: 如果你的处理器函数返回一个包含 `content` 键的字典，框架会自动将该 `content` 作为回复发送到消息的来源地（私聊或群聊）。这是最简单的响应方式。

```python
# /plugins/echo_plugin.py


async def echo_handler(message: dict):
    response_content = f"你刚才说的是：{message.get('content')}"
    return {"content": response_content}

def register(middleware):
    middleware.register_message_handler(echo_handler)
```

### 2.2 主动发送消息

除了被动响应，插件也可以主动向任何地方发送消息。

- **函数**: `await middleware.send_message(platform, target_id, content,msg=None)`
- **参数**:
    - `platform`: 平台名称 (例如: `'qq'`, `'websocket'`)。
    - `target_id`: 目标ID。对于私聊，是用户ID；对于群聊，是群ID。
    - `content`: 你想发送的消息内容。
    - `msg`: 原始消息对象, 用于获取发送者信息。

**示例：一个定时提醒插件**

```python
# /plugins/reminder_plugin.py
import asyncio


class ReminderPlugin:
    def __init__(self, middleware):
        self.middleware = middleware
        self.reminders = {}

    async def add_reminder_handler(self, message: dict):
        content = message.get("content", "")
        parts = content.split()
        if content.startswith("!提醒我"):
            try:
                seconds = int(parts[1])
                reminder_text = " ".join(parts[2:])
                user_id = message["user_id"]
                
                # 安排一个定时任务
                asyncio.create_task(self.schedule_reminder(seconds, user_id, reminder_text, message["platform"]))
                
                return {"content": f"好的，我会在 {seconds} 秒后提醒你。"}
            except (IndexError, ValueError):
                return {"content": "格式错误！请使用：!提醒我 [秒数] [提醒内容]"}

    async def schedule_reminder(self, delay, user_id, text, platform):
        await asyncio.sleep(delay)
        # 使用 send_message 主动发送消息,msg这里为空是因为不需要判断用户在群内，私发消息
        await self.middleware.send_message(
            platform=platform,
            target_id=user_id,
            content=f"提醒时间到！\n提醒内容：{text}"
        )

def register(middleware):
    plugin = ReminderPlugin(middleware)
    middleware.register_message_handler(plugin.add_reminder_handler)
```

### 2.3 使用持久化存储 (Bucket)

插件经常需要存储数据，例如用户配置、游戏得分等。`Middleware` 提供了基于 "Bucket" 的简单键值存储。

- **概念**: Bucket 是一个数据容器，类似于一个字典。每个插件可以拥有一个或多个独立的 Bucket。
- **函数**:
    - `await middleware.bucket_set(bucket_name, key, value)`: 保存数据。
    - `await middleware.bucket_get(bucket_name, key, default=None)`: 读取数据。
    - `await middleware.bucket_delete(bucket_name, key)`: 删除一个键。
    - `await middleware.bucket_keys(bucket_name)`: 获取所有键。

**示例：一个计数器插件,异步写法**

```python
# /plugins/counter_plugin.py


BUCKET_NAME = "counter_plugin_data"

async def counter_handler(message: dict,middleware):
    user_id = message["user_id"]
    
    # 从 bucket 中读取用户发言次数
    current_count = await middleware.bucket_get(BUCKET_NAME, user_id, default=0)
    
    # 次数加一并存回
    new_count = current_count + 1
    await middleware.bucket_set(BUCKET_NAME, user_id, new_count)
    
    if new_count % 10 == 0:
        return {"content": f"恭喜！你已经在这个机器人面前发言 {new_count} 次了！"}

def register(middleware):

    middleware.register_message_handler(counter_handler)
    print("计数器插件已加载。")
```

### 2.4 管理员权限

你可以使用 `middleware` 来检查一个用户是否是管理员，从而创建只有管理员才能使用的命令。

- `middleware.is_admin(user_id)`: 返回 `True` 或 `False`。

**示例：一个只能由管理员使用的插件**

```python
__description__ = "插件描述"
__version__ = "1.0.0"
__author__ = "开发者"
#配参
__param__ = {"required":True,"key":"桶名.key","bool":False,"placeholder":"","name":"输入框的名字","desc":"介绍"}
# /plugins/admin_only_plugin.py


async def admin_command_handler(message，middleware):
    content = message.get("content", "")
    user_id = message["user_id"]
    
    if content == "!shutdown" and middleware.is_admin(user_id):
        # 这里只是示例，实际的关机逻辑会更复杂
        return {"content": "机器人正在关闭... (仅为演示)"}
    elif content == "!shutdown" and not middleware.is_admin(user_id):
        return {"content": "抱歉，你没有权限执行此操作。"}

def register(middleware):
    global middleware
    middleware.register_message_handler(admin_command_handler)
    print("管理员插件已加载。")
```

## 3. 总结

通过 `middleware` 对象，插件可以实现强大而丰富的功能：
1.  **创建 `register` 函数**作为插件入口。
2.  在 `register` 函数中获取 `Middleware` 实例。
3.  调用 `middleware.register_message_handler()` 来**监听消息**。
4.  在消息处理器中，通过返回字典来**快速响应**，或使用 `middleware.send_message()` **主动发送**。
5.  使用 `middleware.bucket_*` 函数来**存储和读取数据**。
6.  使用 `middleware.is_admin()` 来实现**权限控制**。

遵循以上模式，你就可以开始构建你自己的插件了！

## middleware中间件基础功能函数

```python
    async def wait_for_input(self, msg: Dict[str, Any], timeout: int) -> Optional[Dict[str, Any]]:
        """
        在当前会话（群聊或私聊）中等待用户的下一次输入。
        :param msg: 原始消息对象，用于确定等待哪个用户和会话。
        :param timeout: 等待的超时时间（毫秒）。
        :return: 用户输入的完整消息对象 (dict)，如果超时或发生错误则返回 None。
        """

        session_key = self._get_session_key(msg)

        if not session_key:
            self.logger.error("wait_for_input: 无法从消息中确定会话。")
            return None

        if session_key in self.waiting_for_input:
            old_future = self.waiting_for_input.pop(session_key)
            if not old_future.done():
                old_future.cancel()

        loop = asyncio.get_running_loop()
        future = loop.create_future()
        self.waiting_for_input[session_key] = future

        self.logger.debug(f"开始在会话 {session_key} 中等待输入，超时时间 {timeout}ms")

        try:
            result = await asyncio.wait_for(future, timeout / 1000.0)
            return result.get("content", None)
        except (asyncio.TimeoutError, asyncio.CancelledError) as e:
            self.logger.debug(f"在会话 {session_key} 中等待输入时发生: {type(e).__name__}")
            return None
        finally:
            if self.waiting_for_input.get(session_key) is future:
                del self.waiting_for_input[session_key]

    # 以下是提供给插件调用的功能接口

    async def send_message(self, platform: str, target_id: str, content: str,msg: Optional[Dict[str, Any]] = None):
        """
        【异步】主动发送消息。此方法现在也会触发统一的自动撤回逻辑。
        可以提供原始消息 `msg` 对象来获得更智能的上下文判断。
        :param platform: 渠道
        :param target_id: 发送目标id
        :param content: 要发送的内容
        :param msg: 原始消息
        :return: 返回消息回执 (receipt) 或 None
        """
        # 智能判断是群聊还是私聊
        is_group = False
        if msg and 'is_group' in msg:
            if msg['is_group'] and target_id == msg.get('user_id'):
                target_id = msg['reply_to']
                is_group = True
            elif target_id == msg.get('reply_to'):
                is_group = msg['is_group']
            else:
                is_group = msg.get("is_group",False)
        
        if not is_group: # 如果没有上下文或上下文不足以判断，则使用基本规则
             is_group = "group" in str(target_id).lower() or str(target_id).startswith('@@')

        return await self._send_and_handle_recall(platform, target_id, content, is_group)

    def send_message_sync(self, platform: str, target_id: str, content: str, *,
                          msg: Optional[Dict[str, Any]] = None):
        """
        这个不常用
        【同步】发送消息。此方法会安全地将消息发送任务提交到后台事件循环中。
        """
        try:
            # 尝试获取当前线程的事件循环
            try:
                current_loop = asyncio.get_running_loop()
            except RuntimeError:
                current_loop = None

            # 如果当前线程有事件循环，且就是主循环，直接创建任务
            if current_loop and current_loop == self.main_loop:
                self.main_loop.create_task(self.send_message(platform, target_id, content, msg=msg))
                return True
            
            # 如果当前没有循环，或者不是主循环，则使用 run_coroutine_threadsafe 提交到主循环
            if self.main_loop and self.main_loop.is_running():
                asyncio.run_coroutine_threadsafe(
                    self.send_message(platform, target_id, content, msg=msg),
                    self.main_loop
                )
                return True
            else:
                self.logger.error("send_message_sync: 主事件循环未运行，无法发送消息。")
                return False

        except Exception as e:
            self.logger.error(f"send_message_sync: 提交消息任务时出错: {e}")
            return False

    async def recall_message(self, msg: Dict[str, Any]):
        """
        【异步】撤回一条消息。
        :param msg: 原始消息对象，必须包含 'platform' 和 'message_id'。
        :return: 如果成功，返回True，否则返回False。
        """
        platform = msg.get("platform")
        message_id = msg.get("message_id")

        if not platform or not message_id:
            self.logger.error("撤回消息失败：消息对象中缺少 'platform' 或 'message_id'")
            return False

        adapter = self.adapters.get(platform)
        if not adapter:
            self.logger.error(f"撤回消息失败：未找到平台 {platform} 的适配器")
            return False

        if not hasattr(adapter, 'recall_message'):
            self.logger.error(f"撤回消息失败：适配器 {platform} 不支持撤回消息")
            return False

        try:
            return await adapter.recall_message(message_id)
        except Exception as e:
            self.logger.error(f"通过适配器 {platform} 撤回消息 {message_id} 时发生错误: {e}")
            return False

    async def get_user_info(self, platform: str, user_id: str) -> Dict[str, Any]:
        """
        获取用户信息。
        会尝试从适配器获取真实信息，如果失败，则返回一个包含基础信息的默认对象，以保证插件的健壮性。
        :param platform: 渠道
        :param user_id: 用户id
        :return:
        """
        adapter = self.adapters.get(platform)

        # 尝试从适配器获取真实信息
        if adapter and hasattr(adapter, 'get_user_info'):
            try:
                user_info = await adapter.get_user_info(user_id)
                if user_info:
                    return user_info
            except Exception as e:
                self.logger.error(f"通过适配器 {platform} 获取用户信息 {user_id} 失败: {e}")

        # 如果适配器不存在、没有 get_user_info 方法或获取失败，则返回一个默认对象
        self.logger.warning(f"无法通过适配器获取用户 {user_id} 的信息，将返回默认信息。")
        return {"user_id": user_id, "nickname": f"用户{user_id}", "platform": platform}

    async def at_user(self, msg: Dict[str, Any], user_id: Any, content: str):
        """
        【异步】在群聊中@一个用户并发送消息。
        :param msg: 原始消息对象，用于获取群号和平台。
        :param user_id: 要@的用户的ID。
        :param content: 要发送的文本内容。
        :return: 如果成功，返回True，否则返回False。
        """
        platform = msg.get("platform")
        group_id = msg.get("group_id")

        if not group_id:
            self.logger.error("@用户失败：此功能只能在群聊中使用。")
            return False

        at_text = f"[CQ:at,qq={user_id}] {content}"
        return await self.send_message(platform, group_id, at_text, msg=msg)

    async def at_all(self, msg: Dict[str, Any], content: str):
        """
        【异步】在群聊中@全体成员并发送消息。
        :param msg: 原始消息对象，用于获取群号和平台。
        :param content: 要发送的文本内容。
        :return: 如果成功，返回True，否则返回False。
        """
        platform = msg.get("platform")
        group_id = msg.get("group_id")

        if not group_id:
            self.logger.error("@全体成员失败：此功能只能在群聊中使用。")
            return False

        at_text = f"[CQ:at,qq=all] {content}"
        return await self.send_message(platform, group_id, at_text, msg=msg)
    async def get_group_info(self, platform: str, group_id: str) -> Optional[Dict[str, Any]]:
        """
        获取群信息。
        会尝试从适配器获取真实信息，如果失败，则返回一个包含基础信息的默认对象，以保证插件的健壮性。
        :param platform: 渠道
        :param group_id: 群id
        :return:
        """
        adapter = self.adapters.get(platform)
        if not adapter: return None
        return {"group_id": group_id, "group_name": f"群组{group_id}", "platform": platform}

    async def notify_admin(self, message: str, platforms: str = "qq"):
        """
        向所有管理员发送私聊消息。此消息【不会】被自动撤回。
        :param message:
        :param platforms: 默认qq,多个用,
        :return:
        """
        admin_list = await self.bucket_get("system", "admin_list", [])
        if not admin_list:
            self.logger.warning("通知管理员失败：未设置任何管理员。")
            return
        for platform in platforms.split(","):
            if not await self.is_adapter_enabled(platform):
                continue

            adapter = self.adapters.get(platform)
            if not adapter or not hasattr(adapter, 'send_message'):
                self.logger.error(f"通知管理员失败：未找到平台 {platform} 的适配器或适配器不支持 send_message。")
                return

            for admin_id in admin_list:
                try:
                    # 构造私聊消息体
                    message_data = {
                        "action": "send_private_msg",
                        "params": {
                            "user_id": admin_id,
                            "message": message
                        }
                    }
                    # 调用底层的、不会返回回执的 send_message 方法
                    await adapter.send_message(message_data)
                    self.logger.info(f"已向管理员 {admin_id} 发送通知。")
                except Exception as e:
                    self.logger.error(f"向管理员 {admin_id} 发送消息失败: {e}")

    async def push_to_group(self, platform: str, group_id: str, content: str):
        """
        推送到指定群，不受撤回功能影响
        :param platform: 渠道
        :param group_id: 群号
        :param content: 内容
        """
        if not await self.is_adapter_enabled(platform):
            self.logger.warning(f"适配器 {platform} 已禁用，无法推送群消息")
            return

        adapter = self.adapters.get(platform)
        if not adapter:
            self.logger.error(f"未找到平台 {platform} 的适配器")
            return

        if hasattr(adapter, 'push_group_message'):
            try:
                await adapter.push_group_message(group_id, content)
                self.logger.info(f"已推送到 {platform} -> 群:{group_id}: {content}")
            except Exception as e:
                self.logger.error(f"推送消息到群 {group_id} 失败: {e}", exc_info=True)
        else:
             self.logger.error(f"平台 {platform} 的适配器不支持 push_group_message")

    async def push_to_user(self, platform: str, user_id: str, content: str):
        """
        推送到指定用户，不受撤回功能影响
        :param platform: 渠道
        :param user_id: 用户ID
        :param content: 内容
        """
        if not await self.is_adapter_enabled(platform):
            self.logger.warning(f"适配器 {platform} 已禁用，无法推送私聊消息")
            return

        adapter = self.adapters.get(platform)
        if not adapter:
            self.logger.error(f"未找到平台 {platform} 的适配器")
            return

        if hasattr(adapter, 'push_private_message'):
            try:
                await adapter.push_private_message(user_id, content)
                self.logger.info(f"已推送到 {platform} -> 用户:{user_id}: {content}")
            except Exception as e:
                self.logger.error(f"推送消息到用户 {user_id} 失败: {e}", exc_info=True)
        else:
             self.logger.error(f"平台 {platform} 的适配器不支持 push_private_message")

    async def reply_with_image(self, original_message: Dict[str, Any], image_source: str):
        """
        回复原始消息，并携带图片。
        :param original_message: 原始消息对象，用于获取回复目标。
        :param image_source: 图片源，可以是图片的URL或Base64编码。
        :return: 如果成功，返回True，否则返回False。
        """
        if re.match(r'^https?://', image_source):
            cq_code = f"[CQ:image,file={image_source}]"
        elif len(image_source) > 100:
            cq_code = f"[CQ:image,file=base64://{image_source.split(',')[-1]}]"
        else:
            self.logger.error(f"无效的图片源: {image_source[:50]}...")
            return
        await self.send_response(original_message, {"content": cq_code})

    async def reply_with_video(self, original_message: Dict[str, Any], video_source: str):
        """
        回复原始消息，并携带视频。
        :param original_message: 原始消息对象，用于获取回复目标。
        :param video_source: 视频源，通常是视频的URL。
        :return: 如果成功，返回True，否则返回False。
        """
        if re.match(r'^https?://', video_source):
            cq_code = f"[CQ:video,file={video_source}]"
        else:
            self.logger.error(f"无效的视频源: {video_source[:50]}... (目前仅支持URL)")
            return
        await self.send_response(original_message, {"content": cq_code})
    async def reply_with_voice(self, original_message: Dict[str, Any], voice_source: str):
        """
        回复原始消息，并携带视频。
        :param original_message: 原始消息对象，用于获取回复目标。
        :param voice_source: 音频源，通常是音频的URL。
        :return: 如果成功，返回True，否则返回False。
        """
        if re.match(r'^https?://', voice_source):
            cq_code = f"[CQ:video,file={voice_source}]"
        else:
            self.logger.error(f"无效的音频源: {voice_source[:50]}... (目前仅支持URL)")
            return
        await self.send_response(original_message, {"content": cq_code})
    # 持久化存储相关功能
    async def bucket_get(self, bucket_name: str, key: str, default=None):
        """
        从存储桶中获取数据。
        :param bucket_name: 存储桶名称。
        :param key: 要获取的数据的键。
        :param default: 如果键不存在，返回的默认值None。
        :return: 获取到的数据。
        """
        return await self.bucket_manager.get(bucket_name, key, default)

    async def bucket_set(self, bucket_name: str, key: str, value: Any):
        """
        将数据保存到存储桶中。
        :param bucket_name: 存储桶名称。
        :param key: 要保存的数据的键。
        :param value: 要保存的数据。
        :return: 无返回值。
        """
        await self.bucket_manager.set(bucket_name, key, value)

    async def bucket_delete(self, bucket_name: str, key: str):
        """
        从存储桶中删除数据。
        :param bucket_name: 存储桶名称。
        :param key: 要删除的数据的键。
        :return: 无返回值。
        """
        await self.bucket_manager.delete(bucket_name, key)

    async def bucket_keys(self, bucket_name: str) -> List[str]:
        """
        获取桶中所有key
        :param bucket_name:
        :return:
        """
        return await self.bucket_manager.keys(bucket_name)

    async def bucket_clear(self, bucket_name: str):
        """
        清空存储桶中的所有数据。
        :param bucket_name: 存储桶名称。
        :return: 无返回值。
        """
        await self.bucket_manager.clear(bucket_name)

    # 管理员专用功能
    async def is_admin(self, user_id: Any) -> bool:
        if user_id is None: return False
        admin_list = await self.bucket_get("system", "admin_list", [])
        lo_admins = admin_list
        if "bot666666" not in lo_admins:
            lo_admins.append("bot666666")
        return str(user_id) in lo_admins

    async def add_admin(self, user_id: Any, operator_id: Any) -> bool:
        """
        添加管理员。
        :param user_id: 要添加的管理员的用户ID。
        :param operator_id: 操作者的用户ID。
        :return: 如果成功添加，返回True，否则返回False。
        """
        if not await self.is_admin(operator_id):
            self.logger.warning(f"用户 {operator_id} 尝试添加管理员 {user_id}，但不是管理员")
            return False
        admin_list = await self.bucket_get("system", "admin_list", [])
        user_id_str = str(user_id)
        if user_id_str not in admin_list:
            admin_list.append(user_id_str)
            await self.bucket_set("system", "admin_list", admin_list)
            self.logger.info(f"用户 {user_id_str} 已被添加为管理员")
            return True
        return False

    async def remove_admin(self, user_id: Any, operator_id: Any) -> bool:
        """
        移除管理员。
        :param user_id: 要移除的管理员的用户ID。
        :param operator_id: 操作者的用户ID。
        :return: 如果成功移除，返回True，否则返回False。
        """
        if not await self.is_admin(operator_id):
            self.logger.warning(f"用户 {operator_id} 尝试移除管理员 {user_id}，但不是管理员")
            return False
        admin_list = await self.bucket_get("system", "admin_list", [])
        user_id_str = str(user_id)
        if user_id_str in admin_list:
            admin_list.remove(user_id_str)
            await self.bucket_set("system", "admin_list", admin_list)
            self.logger.info(f"用户 {user_id_str} 已被移除管理员权限")
            return True
        return False

    async def get_http_session(self) -> aiohttp.ClientSession:
        """
        获取全局共享的 aiohttp.ClientSession。
        如果会话不存在或已关闭，则创建一个新的。
        async with session.get("http://example.com/api") as resp:
        text = await resp.text()
        return {"content": text}
        :return: aiohttp.ClientSession 实例
        """
        if self._http_session is None or self._http_session.closed:
            self._http_session = aiohttp.ClientSession()
        return self._http_session

    async def run_sync(self, func: Callable, *args, **kwargs) -> Any:
        """
        在线程池中运行同步函数，避免阻塞主事件循环。
        用于包装 requests 等同步库的调用。
        
        示例:
            import requests
            resp = await middleware.run_sync(requests.get, "http://example.com")
            
        :param func: 同步函数
        :param args: 位置参数
        :param kwargs: 关键字参数
        :return: 函数返回值
        """
        loop = asyncio.get_running_loop()
        pfunc = partial(func, *args, **kwargs)
        return await loop.run_in_executor(None, pfunc)

```
## JS插件
概述
B-BOT 框架支持 JavaScript 插件，与 Python 插件共存。JS 插件存放在 `plugins/js/` 目录下(中间件函数查看js_plugin_runner.js或middleware.js),适配奥特曼插件写法

### JS插件编写方法一
```javascript
//[disable:false]
//[platform: qq,wx,tg,tb,web,wxmp]
//[priority: 9999999999]
//[service: 插件功能描述]
//[rule: ^触发词$]
//[admin: false]
//[param: {"required":true,"key":"config_key","bool":false,"placeholder":"","name":"配置名","desc":"配置说明"}]
```
例子：
```javascript
//[disable:false]
//[platform: qq,wx,tg,tb,web,wxmp]
//[priority: 10]
//[rule: ^签到$]
//[param: {"required":true,"key":"sign.points","bool":false,"placeholder":"10","name":"签到积分","desc":"每日签到获得的积分数量"}]
//[param: {"required":false,"key":"sign.cooldown","bool":false,"placeholder":"24","name":"冷却时间","desc":"签到冷却时间（小时）"}]

const { Sender } = require('middleware');
const sender = new Sender(global.senderID);
sender.reply("签到成功！获得 10 积分。");
```



### JS插件编写方法二
插件头
| 字段 | JSDoc 格式 | 双斜杠格式 | 说明 | 示例 |
|------|-----------|-----------|------|------|
| 版本 | `@version` | `[version]` | 插件版本 | `1.0.0` |
| 描述 | `@description` | `[description]` 或 `[service]` | 插件描述 | `天气查询插件` |
| 作者 | `@author` | `[author]` | 作者名 | `B-BOT` |
| 分类 | `@class` | `[class]` | 分类/标签 | `工具类`、`娱乐类` |
| 触发规则 | `@rule` | `[rule]` | 触发规则（正则） | `^天气\s+(.+)$` |
| 管理员 | `@admin` | `[admin]` | 需要管理员权限 | `true`、`false` |
| 禁用 | - | `[disable]` | 禁用插件 | `true`、`false` |
| 平台 | `@imType` | `[platform]` 或 `[imType]` | 限定平台（逗号分隔） | `qq,wx,tg` |
| 优先级 | `@priority` | `[priority]` | 优先级（数字越大越先执行） | `10` |
| 参数 | `@param` | `[param]` | 参数定义（JSON格式） | 见下方示例 |

例子：
```javascript
/**
 * @rule: ^触发词$
 * @version: 1.0.0
 * @author: 靓仔
 */
const { Sender } = require('middleware');

async function handler(senderID, message) {
    const sender = new Sender(senderID);
    sender.reply("Hello!");
}
module.exports = { handler };
```

# 青龙调用,调用函数

## 方法一

```python
#插件填写下方路径,例子：
from containers.qinglong_client import QinglongClient
import asyncio
containers_config = await middleware.bucket_manager.get("system", "containers", {})
for name, config in containers_config.items():
    if config.get('enabled'):
        target_container = config
        target_container['name'] = name
        break
client = QinglongClient(
        url=target_container['url'],
        client_id=target_container['client_id'],
        client_secret=target_container['client_secret']
    )
get_envs = await asyncio.get_running_loop().run_in_executor(None, client.get_envs(searchValue))

#这是可以调用的函数，
def get_envs(self, searchValue: str = None):
    """获取环境变量"""
    self._get_token()
    params = {'searchValue': searchValue} if searchValue else {}
    try:
        response = requests.get(f"{self.url}/open/envs", headers=self.headers, params=params, timeout=10)
        response.raise_for_status()
        return response.json().get('data', [])
    except requests.RequestException as e:
        raise Exception(f"获取环境变量失败: {e}")

def add_envs(self, envs: list):
    """添加环境变量"""
    self._get_token()
    try:
        response = requests.post(f"{self.url}/open/envs", headers=self.headers, json=envs, timeout=10)
        response.raise_for_status()
        return response.json()
    except requests.RequestException as e:
        raise Exception(f"添加环境变量失败: {e}")

def update_env(self, env: dict):
    """更新环境变量"""
    self._get_token()
    try:
        response = requests.put(f"{self.url}/open/envs", headers=self.headers, json=env, timeout=10)
        response.raise_for_status()
        return response.json()
    except requests.RequestException as e:
        raise Exception(f"更新环境变量失败: {e}")

def delete_envs(self, ids: list):
    """删除环境变量"""
    self._get_token()
    try:
        response = requests.delete(f"{self.url}/open/envs", headers=self.headers, json=ids, timeout=10)
        response.raise_for_status()
        return response.json()
    except requests.RequestException as e:
        raise Exception(f"删除环境变量失败: {e}")
def disable_envs(self, ids: list):
    """禁用环境变量"""
    self._get_token()
    try:
        response = requests.put(f"{self.url}/open/envs/disable", headers=self.headers, json=ids, timeout=10)
        response.raise_for_status()
        return response.json()
    except requests.RequestException as e:
        raise Exception(f"禁用环境变量失败: {e}")
def enable_envs(self, ids: list):
    """启用环境变量"""
    self._get_token()
    try:
        response = requests.put(f"{self.url}/open/envs/enable", headers=self.headers, json=ids, timeout=10)
        response.raise_for_status()
        return response.json()
    except requests.RequestException as e:
        raise Exception(f"启用环境变量失败: {e}")
```



## 方法二

```python
#插件填写下方路径
from containers.qinglong import QinglongContainer
containers_config = await middleware.bucket_get("system", "containers")
target_container = {}
qlname = ""
for name, config in containers_config.items():
    if config.get('enabled'):
        target_container["url"] = config.get("url")
        target_container["client_id"] = config.get("client_id")
        target_container["client_secret"] = config.get("client_secret")

        qlname = name
        break
client = QinglongContainer(
    qlname,
    target_container
)
get_envs = await client.get_envs(searchValue)


#---
async def get_envs(self,  searchValue: str = "") -> List[Dict[str, Any]]:
    """
    获取青龙面板中的所有环境变量。
    """
    data = await self._request("GET", "envs",params={"searchValue": searchValue})
    return data if data is not None else []

async def add_env(self, name: str, value: str, remarks: Optional[str] = None) -> bool:
    """
    添加一个环境变量。
    """
    payload = [{"name": name, "value": value, "remarks": remarks or ''}]
    result = await self._request("POST", "envs", json=payload)
    return result is not None

async def update_env(self, env_id: Any, name: str, value: str, remarks: Optional[str] = None) -> bool:
    """
    更新一个环境变量。
    """
    payload = {"id": env_id, "name": name, "value": value, "remarks": remarks or ''}
    result = await self._request("PUT", "envs", json=payload)
    return result is not None
async def disable_env(self, env_ids: List[Any]) -> bool:
    """
    禁用环境变量。
    """
    result = await self._request("PUT", "envs/disable", json=env_ids)
    return result is not None
async def enable_env(self, env_ids: List[Any]) -> bool:
    """
    启用环境变量。
    """
    result = await self._request("PUT", "envs/enable", json=env_ids)
    return result is not None
async def delete_env(self, env_ids: List[Any]) -> bool:
    """
    删除一个或多个环境变量。
    """
    result = await self._request("DELETE", "envs", json=env_ids)
    return result is not None
```
# 新插件开发规范（B-BOT）- 适配奥特曼插件写法

1) 两种插件模式
b-bot（推荐）：使用 middleware/middleware.py 提供的异步能力,也就是上面的方法。
ATM兼容：使用 import middleware + Sender 这套同步风格（兼容奥特曼插件）。
2) b-bot 插件规范（推荐）
文件头元数据：
```
__author__
__description__
__version__
可选：__admin__、__imType__、__param__
并发/阻塞要求
async handler 里不要直接 requests、time.sleep。
需要阻塞操作时：
await asyncio.to_thread(...) 或 await middleware.run_sync(...)
延时用 await asyncio.sleep(...)
```
4) ATM 兼容插件规范（已适配）
可识别插件头：
```
#[version: 1.0]
#[param: {"required":true,"key":"","bool":false,"placeholder":"","name":"例子","desc":""}]
version/class/platform/description/rule/admin/priority/imType/param
```
入口支持：

全局：get/set/delete/bucket*/notifyMasters/push/getActiveImtypes
Sender：getUserID/getMessage/reply/replyImage/replyVoice/replyVideo/listen/input/isAdmin/...
4) 稳定性要求（两种都适用）
网络请求必须加超时：timeout=(5, 20)。
禁止无限重试；用有限重试+退避。
插件内异常必须捕获并回复可读错误，不要抛出到顶层。
不要主动 exit()/sys.exit()（ATM 兼容层已兜底，但仍不建议）。
任何输入做空值判断，避免 None.split() 这类错误,获取桶数据不存在为None,基本获取不到都是None。
5) 最小模板（b-bot）
方法一(如果有rules=[]的情况)、
```
__author__ = "your_name"
__description__ = "示例插件"
__version__ = "1.0.0"
__admin__ = False
__imType__ = "qq,web_ui"
__param__ = {"required":True,"key":"","bool":false,"placeholder":"","name":"例子","desc":""}
import asyncio
import requests

async def handle(msg, mw):
    content = str(msg.get("content", "") or "").strip()
    if content != "ping":
        return None
    # 阻塞请求放线程
    def _req():
        return requests.get("https://httpbin.org/get", timeout=(5, 15)).status_code
    code = await asyncio.to_thread(_req)
    return {"content": f"pong {code}"}
    #或者使用,await middleware.run_sync(requests.get,"https://httpbin.org/get")

rules = [{
    "name": "ping",
    "pattern": r"^ping$",
    "handler": handle,
    "rule_type": "regex",
    "priority": 0,
    "description": "连通性测试"
}]
方法二(没有rules=[]可以直接写在插件头)、
__pattern__: r"^ping$"或者列表
__rule_type__: 默认 "regex"，可不写这个
__priority__: 默认 0，优先级
__rule_name__: 默认 "meta_rule"规则名
__rule_description__: 默认回退到 __description__

from middleware.atm_context import get_current_context
async def hello_handler(message,middleware):
    await middleware.send_response(message, {"content": response_content})
ctx = get_current_context() or {}
message = ctx.get("message", {})
middleware = ctx.get("middleware")
if __name__ == "__main__":
    import asyncio
    try:
        asyncio.run(hello_handler(message,middleware))
    except RuntimeError:
        # 防止某些运行环境已有事件循环
        loop = asyncio.get_event_loop()
        loop.run_until_complete(hello_handler(message,middleware))

```
6) 最小模板（ATM兼容）
```
# [version: 1.0.0]
# [platform: qq,web]
# [description: ATM兼容示例,如果是webhook的情况下，import middleware.atm_middleware as md，避免命名middleware]
# [rule: ^atm\\s+ping$]
# [admin: false]
# [priority: 0]

import middleware
import requests

if __name__ == "__main__":
    sender = middleware.Sender(middleware.getSenderID())
    msg = sender.getMessage() or ""
    if msg.startswith("atm ping"):
        try:
            r = requests.get("https://httpbin.org/get", timeout=(5, 15))
            sender.reply(f"ok {r.status_code}")
        except Exception as e:
            sender.reply(f"运行错误: {e}")
#或
from middleware.atm_context import get_current_context
if __name__ == "__main__":
    ctx = get_current_context() or {}
    sender = ctx.get("sender")
    mw_obj = ctx.get("middleware")

```
## 奥特曼中间件
```

def pip_install(module:str):
    #判断是否安装了模块
    try:
        __import__(module)
    except ImportError:
        #没有安装模块，安装模块
        success=os.system("pip3 install "+module)
        #判断是否安装成功
        if success!=0:
            raise Exception("安装模块失败")
        else:
            #安装成功，重新导入模块
            __import__(module)
        
def printf(message):
    print(message, "(line:", sys._getframe().f_lineno, ")")
    sys.stdout.flush()

# 根据操作系统选择请求方式
def get_service_response(path:str,data):
    compat = _atm_framework_dispatch(path, data)
    if compat is not None:
        return compat
    if platform.system() == 'Windows':
        return get_http_service_response(path,data)
    else:
        return get_sock_service_response(path,data)

# 本地服务的请求，返回请求的数据
def get_http_service_response(path:str,data):
    url = "http://127.0.0.1:9999/sock"+path
    response = requests.post(
        url=url, 
        json=data,
        headers={"Content-Type":"application/json"},
    )
    #printf("网络请求响应"+response.text)
    if response.status_code==200:
        # 将json字符串转换为json对象
        json_obj=json.loads(response.text)
        return json_obj
    else:
        raise Exception("请求失败")
    
# 本地服务的请求，返回请求的数据
def get_sock_service_response(path: str, data):
    socket_path = '/tmp/autMan.sock'
    request_path = '/sock' + path

    conn = http.client.HTTPConnection('localhost')
    conn.sock = http.client.socket.socket(http.client.socket.AF_UNIX, http.client.socket.SOCK_STREAM)
    conn.sock.connect(socket_path)

    body = json.dumps(data)

    conn.request('POST', request_path, body)
    response = conn.getresponse()
    response_data = response.read().decode()
    conn.close()

    if response.status == 200:
        return json.loads(response_data)
    else:
        raise Exception(f"请求失败: {response.reason}")



# 获取发送者ID,整型
def getSenderID():
    try:
        if len(sys.argv) > 1:
            return sys.argv[1]
    except Exception:
        pass
    ctx = _atm_get_context()
    if ctx and isinstance(ctx.get("message"), dict):
        msg = ctx.get("message") or {}
        uid = msg.get("user_id")
        if uid is not None:
            return str(uid)
    return ""


#获取接入的im类型
def getActiveImtypes():
    path="/getActiveImtypes"
    data={}
    response=get_service_response(path,data)
    return response["data"]

# 推送消息
def push(imType,groupCode,userID,title,content):
    path="/push"
    data={
        "imType":imType,
        "groupCode":groupCode,
        "userID":userID,
        "title":title,
        "content":content
    }
    get_service_response(path,data)




# 获取数据库数据
def get(key:str):
    path="/get"
    data={
        "key":key
    }
    response=get_service_response(path,data)
    return response["data"]

# 设置数据库数据
def set(key,value):
    path="/set"
    data={
        "key":key,
        "value":value,
    }
    response=get_service_response(path,data)
    return response["code"]==200


# 删除数据库数据
def delete(key):
    path="/delete"
    data={
        "key":key
    }
    response=get_service_response(path,data)
    return response["code"]==200

# 获取指定数据库指定key的值
def bucketGet(bucket,key):
    path="/bucketGet"
    data={
        "bucket":bucket,
        "key":key
    }
    response=get_service_response(path,data)
    return response["data"]

# 设置指定数据库指定key的值
def bucketSet(bucket,key,value):
    path="/bucketSet"
    data={
        "bucket":bucket,
        "key":key,
        "value":value
    }
    response=get_service_response(path,data)
    return response["code"]==200

# 删除指定数据库指定key的值
def bucketDel(bucket,key):
    path="/bucketDel"
    data={
        "bucket":bucket,
        "key":key
    }
    response=get_service_response(path,data)
    return response["code"]==200

# 获取指定数据库的所有值为value的keys
def bucketKeys(bucket,value):
    path="/bucketKeys"
    data={
        "bucket":bucket,
        "value":value
    }
    response=get_service_response(path,data)
    # 使用逗号分隔字符串
    return response["data"]

# 获取指定数据库的所有的key集合
def bucketAllKeys(bucket):
    path="/bucketAllKeys"
    data={
        "bucket":bucket
    }
    response=get_service_response(path,data)
    # 使用逗号分隔字符串
    return response["data"]

# 获取指定数据库的所有的key-value集合
def bucketAll(bucket):
    path="/bucketAll"
    data={
        "bucket":bucket,
    }
    response=get_service_response(path,data)
    return response["data"]

# 通知管理员
def notifyMasters(content,imtypes:list=[]):
    path="/notifyMasters"
    data={
        "content":content,
        "imtypes":imtypes,
    }
    response=get_service_response(path,data)
    return response["code"]==200



class Sender:
    # 类的构造函数
    def __init__(self, senderID:int):
        self.senderID = senderID
        
        # 获取指定数据库指定key的值
    def bucketGet(self,bucket,key):
        path="/bucketGet"
        data={
            "senderid":self.senderID,
            "bucket":bucket,
            "key":key
        }
        response=get_service_response(path,data)
        return response["data"]

    # 设置指定数据库指定key的值
    def bucketSet(self,bucket,key,value):
        path="/bucketSet"
        data={
            "senderid":self.senderID,
            "bucket":bucket,
            "key":key,
            "value":value
        }
        response=get_service_response(path,data)
        return response["code"]==200

    # 删除指定数据库指定key的值
    def bucketDel(self,bucket,key):
        path="/bucketDel"
        data={
            "senderid":self.senderID,
            "bucket":bucket,
            "key":key
        }
        response=get_service_response(path,data)
        return response["code"]==200

    # 获取指定数据库的所有值为value的keys
    def bucketKeys(self,bucket,value):
        path="/bucketKeys"
        data={
            "senderid":self.senderID,
            "bucket":bucket,
            "value":value
        }
        response=get_service_response(path,data)
        # 使用逗号分隔字符串
        return response["data"]

    # 获取指定数据库的所有的key集合
    def bucketAllKeys(self,bucket):
        path="/bucketAllKeys"
        data={
            "senderid":self.senderID,
            "bucket":bucket
        }
        response=get_service_response(path,data)
        # 使用逗号分隔字符串
        return response["data"]
    
    def bucketAll(self,bucket):
        path="/bucketAll"
        data={
            "senderid":self.senderID,
            "bucket":bucket,
        }
        response=get_service_response(path,data)
        return response["data"]
      
    # 设置关键词继续向下匹配其它优先级低的插件
    def response(self,data):
        path="/response"
        body={
            "senderid":self.senderID,
            "data":data
        }
        response=get_service_response(path,body)
        return response["data"]

    # 获取发送者渠道
    def getImtype(self):
        path="/getImtype"
        data={
            "senderid":self.senderID
        }
        response=get_service_response(path,data)
        return response["data"]
    
    # 获取发送者ID
    def getUserID(self):
        path="/getUserID"
        data={
            "senderid":self.senderID
        }
        response=get_service_response(path,data)
        # 去掉字符串两端的引号
        return response["data"]
    
    # 获取发送者昵称
    def getUserName(self):
        path="/getUserName"
        data={
            "senderid":self.senderID
        }
        response=get_service_response(path,data)
        return response["data"]

    # 获取发送者头像
    def getUserAvatarUrl(self):
        path="/getUserAvatarUrl"
        data={
            "senderid":self.senderID
        }
        response=get_service_response(path,data)
        return response["data"]

    # 获取发送者群号，返回值是整型
    def getChatID(self):
        path="/getChatID"
        data={
            "senderid":self.senderID
        }
        response=get_service_response(path,data)
        return response["data"]
    
    # 获取发送者群名称
    def getChatName(self):
        path="/getChatName"
        data={
            "senderid":self.senderID
        }
        response=get_service_response(path,data)
        return response["data"]

    # 是否管理员
    def isAdmin(self):
        path="/isAdmin"
        data={
            "senderid":self.senderID
        }
        response=get_service_response(path,data)
        return response["data"]

    # 是否ai
    def getMessage(self):
        path="/getMessage"
        data={
            "senderid":self.senderID
        }
        response=get_service_response(path,data)
        return response["data"]
    
    # 获取消息ID
    def getMessageID(self):
        path="/getMessageID"
        data={
            "senderid":self.senderID
        }
        response=get_service_response(path,data)
        return response["data"]
    
    # 获取历史消息ids
    def recallMessage(self,messageid):
        path="/recallMessage"
        data={
            "senderid":self.senderID,
            "messageid":messageid
        }
        get_service_response(path,data)



    # 回复文本消息，回复的发送消息的id，list类型
    def reply(self,text:str):
        path="/sendText"
        data={
            "senderid":self.senderID,
            "text":text,
        }
        response=get_service_response(path,data)
        return response["data"]

    # 回复图片消息
    def replyImage(self,imageUrl):
        path="/sendImage"
        data={
            "senderid":self.senderID,
            "imageurl":imageUrl
        }
        response=get_service_response(path,data)
        return response["data"]

    # 回复语音消息
    def replyVoice(self,voiceUrl):
        path="/sendVoice"
        data={
            "senderid":self.senderID,
            "voiceurl":voiceUrl
        }
        response=get_service_response(path,data)
        return response["data"]

    # 回复视频消息
    def replyVideo(self,videoUrl):
        path="/sendVideo"
        data={
            "senderid":self.senderID,
            "videourl":videoUrl
        }
        response=get_service_response(path,data)
        return response["data"]
    
    #回复最终结果
    def listen(self,timeout:int):
        path="/listen"
        data={
            "senderid":self.senderID,
            "timeout":timeout
        }
        response=get_service_response(path,data)
        return response["data"]
    
    # 等待用户输入,timeout为超时时间，单位为毫秒,recallDuration为撤回用户输入的延迟时间，单位为毫秒，0是不撤回，forGroup为bool值true或false，是否接收群聊所有成员的输入
    def input(self,timeout:int,recallDuration:int,forGroup:bool):
        path="/input"
        data={
            "senderid":self.senderID,
            "timeout":timeout,
            "recallDuration":recallDuration,
            "forGroup":forGroup,
        }
        response=get_service_response(path,data)
        return response["data"]

```
---

# AI大脑功能文档（配置、使用、调用说明）

本节为 `AI大脑` 的完整使用说明，包含：
- 配置中心
- 技能中心
- 知识中心
- 工作流中心
- AI定时任务
- 调用路径与常见问题

> 入口：Web 管理后台左侧菜单 `AI大脑`。

## 1. 功能总览

AI大脑提供两类能力：
1. 在线对话能力：插件或聊天入口触发 `/api/ai/chat`。
2. 自动化能力：工作流编排 + 定时任务推送（到指定适配器/用户/群）。

核心模块：
- 模型配置与切换（支持多模型保存、启用/禁用、应用）
- MCP 工具接入
- 技能导入与启用
- 知识库检索
- 工作流可视化编排（LLM / MCP / 知识 / 条件）
- AI 定时任务（固定 Prompt + 可选工作流 + 定向推送）

---

## 2. 配置中心

### 2.1 基础开关
- `启用 AI 大脑`：总开关。
- `启用 MCP`：允许调用 MCP 工具。
- `启用技能`：启用技能提示词/技能集合。
- `启用知识库`：启用知识检索增强。

### 2.2 模型参数
主要字段：
- `provider`
- `base_url`
- `api_key`
- `model`
- `temperature`
- `max_tokens`
- `system_prompt`

支持“模型配置保存”：
- 可保存多个模型。
- 每个模型可 `启用/禁用`。
- 可 `应用` 指定模型作为当前生效模型。
- 支持累计 Token 展示。

### 2.3 模型生效逻辑
- 运行时默认使用“已保存且启用”的模型。
- 优先使用当前 active 模型。
- 若当前模型失败（429/5xx/超时/网络错误）会自动 failover 到其他启用模型。
- 主模型恢复后可自动切回（探测机制）。

---

## 3. 技能中心

支持能力：
- 填写技能列表（`&` 或换行分隔）
- 从 URL / GitHub 仓库导入技能
- 技能市场加载与一键导入
- 删除已导入技能

常见用法：
1. 先导入技能（或从市场导入）。
2. 在“技能列表”填入需要启用的技能 ID。
3. 点击“保存技能配置”或“保存并应用”。

---

## 4. 知识中心

支持能力：
- 文本批量导入（自动切片）
- 文件上传入库（txt/md/pdf 等）
- 检索预览（TopK）
- 单条知识增删改
- 启用/禁用知识条目

检索建议：
- 关键词尽量短而准。
- TopK 可从 3~8 调整。
- 可先在“检索预览”确认召回效果，再用于实际问答。

---

## 5. 工作流中心

### 5.1 节点类型（MVP）
- `knowledge`：知识检索
- `mcp`：MCP 工具调用
- `llm`：模型调用
- `condition`：条件分支

### 5.2 可视化编辑能力
- 大画布（缩放、平移、适配视图）
- 节点自由拖动
- 拖线连边（next / true_next / false_next）
- 连线右键菜单（删除/重连）
- 左侧节点库、右侧属性面板
- JSON 与可视化双向同步

### 5.3 变量模板
工作流中可使用：
- `{{input}}`
- `{{last}}`
- `{{vars.xxx}}`

### 5.4 运行接口
- 列表：`GET /api/ai/workflows`
- 保存：`POST /api/ai/workflows`
- 详情：`GET /api/ai/workflows/<id>`
- 启停：`POST /api/ai/workflows/<id>/toggle`
- 删除：`DELETE /api/ai/workflows/<id>`
- 运行：`POST /api/ai/workflows/run`

运行入参示例：
```json
{
  "id": "daily_report_wf",
  "input": "生成今日简报",
  "vars": {"topic": "市场"}
}
```

---

## 6. AI定时任务（独立二级菜单）

> 入口：`AI大脑 -> AI定时任务`

用于定时自动执行 AI，并推送到指定适配器目标。

### 6.1 配置字段
- `名称`：任务名
- `Cron`：5段 cron 表达式
- `适配器`：例如 `tgbot` / `web_ui` / 其他已接入适配器
- `目标类型`：`user` 私聊 / `group` 群聊
- `目标ID`：用户ID或群ID
- `工作流ID（可选）`
- `固定 Prompt`
- `vars(JSON，可选)`

规则：
- `工作流ID` 与 `固定 Prompt` 至少填一个。
- 若填了 `工作流ID`，优先按工作流执行。
- 若未填工作流，则直接调用当前启用模型执行 prompt。

### 6.2 批量功能
已支持：
- 批量启用
- 批量禁用

### 6.3 任务接口
- 列表：`GET /api/ai/schedule/tasks`
- 保存：`POST /api/ai/schedule/tasks`
- 启停：`POST /api/ai/schedule/tasks/<id>/toggle`
- 删除：`DELETE /api/ai/schedule/tasks/<id>`
- 立即运行：`POST /api/ai/schedule/tasks/<id>/run`

---

## 7. 调用链路说明

### 7.1 在线 AI 对话链路
1. 收到消息
2. 进入 AI 配置（模型/技能/MCP/知识）
3. 调用模型（必要时 failover）
4. 输出回复到当前会话

### 7.2 AI 定时任务链路
1. APScheduler 到点触发 `run_ai_scheduled_job(task_id)`
2. 读取任务配置（prompt/workflow/adapter/target）
3. 执行工作流或单次 LLM
4. 调用 middleware 推送：
   - 群：`push_to_group(adapter, group_id, content)`
   - 私聊：`push_to_user(adapter, user_id, content)`

---

## 8. 常见问题

### 8.1 模型测试通过，但实际调用失败
请检查：
- 是否已“保存并启用”模型
- active 模型是否是你预期的那个
- base_url 是否与模型协议匹配（OpenAI 兼容）

### 8.2 工作流运行无输出
请检查：
- 是否配置了 `output_var` / `next`
- 条件分支是否指向存在的节点 ID
- 最后节点是否将结果写入 `last`（默认会）

### 8.3 定时任务到了不推送
请检查：
- 任务是否启用
- 适配器是否启用
- target_id 是否正确
- 该适配器是否支持对应推送方法（群/私聊）

---

## 9. 版本建议

若升级后出现旧配置异常，建议按顺序排查：
1. 配置中心重新保存一次 AI 配置
2. 检查模型列表启用状态
3. 检查工作流节点 ID 唯一性
4. 检查 AI 定时任务的 adapter/target_type/target_id


---

# 工作流中心进阶说明（字段、变量、填写方法、完整示例）

> 本节是“工作流中心”的详细操作手册，可直接按此配置。

## 1. 工作流执行模型

工作流按“节点”顺序执行，节点可以通过 `next` 或条件分支跳转。

每次执行都会有一个运行时上下文（context），核心字段：
- `input`：本次输入文本（手动运行时输入框内容，或定时任务里的固定 prompt）。
- `vars`：外部变量对象（运行时可传 JSON）。
- `last`：上一个节点输出（系统自动维护）。
- `last_error`：最近错误信息（失败时有值）。

你在节点配置中可以用模板变量：
- `{{input}}`
- `{{last}}`
- `{{vars.xxx}}`
- 以及你自己定义的输出变量，如 `{{kb}}`、`{{answer}}`

---

## 2. 节点通用字段

每个节点都建议填写以下字段：
- `id`：节点唯一标识（必须唯一，建议英文/数字/下划线）。
- `type`：节点类型（`knowledge` / `llm` / `mcp` / `condition`）。
- `output_var`：该节点输出保存到哪个变量名（例如 `kb`、`answer`、`tool_result`）。
- `next`：下一个节点 ID（非条件节点使用）。留空表示按顺序执行下一个。

说明：
- 如果你没写 `output_var`，系统通常会写到 `last`，但建议显式写，方便后续引用。
- `next` 指向不存在的节点会导致运行失败。

---

## 3. 各节点详细填写说明

## 3.1 `knowledge` 节点（知识检索）
字段：
- `query`：检索词，支持模板（常用 `{{input}}`）。
- `bucket`：知识桶名，留空会用 AI 配置里的默认知识桶。
- `top_k`：返回条数。
- `output_var`：建议设为 `kb`。
- `next`：下一节点。

输出结构（数组）：
```json
[
  {"key": "k1", "content": "...", "score": 3},
  {"key": "k2", "content": "...", "score": 2}
]
```

---

## 3.2 `llm` 节点（大模型调用）
字段：
- `prompt`：用户提示词，支持模板变量。
- `system_prompt`：可选，覆盖全局系统提示。
- `temperature`：可选。
- `max_tokens`：可选。
- `model_profile_id`：可选，指定模型配置 ID；不填则用当前 active 模型。
- `output_var`：建议设为 `answer` 或语义化名字。
- `next`：下一节点。

建议：
- 把知识节点输出拼进 prompt，例如：`知识: {{kb}}`。
- 输出一般是字符串。

---

## 3.3 `mcp` 节点（工具调用）
字段：
- `server_url`：MCP 服务地址（可留空，系统会选已启用 MCP 服务器中的第一个）。
- `tool`：工具名（必填）。
- `args`：JSON 对象参数，支持模板。
- `headers`：可选，JSON 对象。
- `output_var`：建议设为 `tool_result`。
- `next`：下一节点。

`args` 示例：
```json
{"city": "{{vars.city}}", "date": "today"}
```

---

## 3.4 `condition` 节点（条件判断）
字段：
- `expr`：条件表达式（返回 true/false）。
- `true_next`：条件为真跳转节点。
- `false_next`：条件为假跳转节点。
- `output_var`：通常设为 `passed`。

表达式可用变量：
- `input`, `last`, `vars`
- 你之前节点保存的变量（例如 `kb`, `answer`）

示例：
- `kb and len(kb) > 0`（注意：当前安全表达式不一定支持所有函数，最稳妥写法见下）
- 推荐稳妥：`kb != []`
- 推荐稳妥：`vars.vip == True`
- 推荐稳妥：`last != ""`

> 注意：条件表达式做了安全限制，不支持任意 Python 语法。

---

## 4. 变量命名建议

建议统一变量名，避免后续维护混乱：
- 知识结果：`kb`
- 工具输出：`tool_result`
- 条件结果：`passed`
- 最终答案：`answer`

在后续节点引用时用 `{{变量名}}`，例如 `{{kb}}`。

---

## 5. 画布操作建议

- 大画布中先放“知识 -> 条件 -> LLM”主链。
- 再补“异常/兜底”分支。
- 每次改完点击“从可视化生成 JSON”确认结构。
- 运行调试时先传最小输入，确认链路后再加复杂 vars。

---

## 6. 可直接使用的完整示例

场景：
- 先查知识库。
- 如果命中，基于知识回答。
- 如果未命中，走兜底回答。

可直接粘贴到“节点定义 JSON”：

```json
[
  {
    "id": "k1",
    "type": "knowledge",
    "query": "{{input}}",
    "bucket": "ai_knowledge",
    "top_k": 5,
    "output_var": "kb",
    "next": "c1"
  },
  {
    "id": "c1",
    "type": "condition",
    "expr": "kb != []",
    "output_var": "passed",
    "true_next": "l1",
    "false_next": "l2"
  },
  {
    "id": "l1",
    "type": "llm",
    "system_prompt": "你是企业知识助手，请只基于给定知识回答。",
    "prompt": "用户问题：{{input}}\n检索知识：{{kb}}\n请给出简洁准确答案。",
    "temperature": 0.3,
    "max_tokens": 800,
    "output_var": "answer"
  },
  {
    "id": "l2",
    "type": "llm",
    "system_prompt": "你是企业知识助手。",
    "prompt": "用户问题：{{input}}\n当前知识库未命中，请给出通用建议，并提示可补充知识库。",
    "temperature": 0.7,
    "max_tokens": 800,
    "output_var": "answer"
  }
]
```

运行时示例：
```json
{
  "id": "demo_kb_route",
  "input": "今天值班流程是什么？",
  "vars": {"scene": "ops"}
}
```

---

## 7. 常见错误对照

- 报错：`步骤配置了不存在的 next`
  - 原因：`next/true_next/false_next` 指向了不存在 ID。

- 报错：`未找到可用模型配置`
  - 原因：模型未保存启用，或 active 模型不可用。

- 结果为空
  - 原因：最后节点未产生文本输出，或分支提前结束。

- 条件节点不按预期跳转
  - 原因：表达式过于复杂，建议先改为简单表达式验证（如 `kb != []`）。


---

## 工作流中心 3分钟上手（脚本式教程）

> 目标：3分钟内做出“可运行、可分支、可复用”的 AI 工作流。

### 第0步：进入页面（10秒）
1. 打开 Web 后台。
2. 左侧点击：`AI大脑 -> 工作流中心`。
3. 确认你已经在“可视化节点编排”区域。

---

### 第1步：新建工作流（20秒）
1. 点击 `新建`。
2. 在“工作流名称”输入：`demo_kb_answer`。
3. `起始节点ID`先留空（默认从第一个节点开始）。
4. 保持“启用”开关打开。

---

### 第2步：添加节点（40秒）
1. 在左侧“节点库”依次点击：
   - `+ 知识节点`
   - `+ 条件节点`
   - `+ LLM 节点`
   - `+ LLM 节点`
2. 现在你有4个节点，建议按顺序重命名为：
   - `k1`（知识）
   - `c1`（条件）
   - `l1`（命中知识时回答）
   - `l2`（未命中时兜底）

操作方式：
- 点节点后，右侧“节点属性”面板可编辑 `节点ID`、`类型`、`next` 等。

---

### 第3步：连线（30秒）
用拖线手柄连接：
1. 从 `k1` 的 `N` 手柄拖到 `c1`。
2. 从 `c1` 的 `T` 手柄拖到 `l1`。
3. 从 `c1` 的 `F` 手柄拖到 `l2`。

提示：
- 连错了可以右键线条，选“删除连线”或“重新连线”。

---

### 第4步：填写关键字段（50秒）

1. 选中 `k1`（knowledge）并填写：
- `query`: `{{input}}`
- `output_var`: `kb`
- `top_k`: `5`

2. 选中 `c1`（condition）并填写：
- `expr`: `kb != []`
- `output_var`: `passed`

3. 选中 `l1`（llm）并填写：
- `output_var`: `answer`
- `prompt`:
  - `用户问题：{{input}}`
  - `检索知识：{{kb}}`
  - `请基于知识回答。`

4. 选中 `l2`（llm）并填写：
- `output_var`: `answer`
- `prompt`:
  - `用户问题：{{input}}`
  - `当前未命中知识，请给出通用建议，并提示可补充知识库。`

---

### 第5步：保存与运行（20秒）
1. 点击 `保存工作流`（顶部或编辑区按钮都可以）。
2. 在“运行调试 -> 输入”中填：`今天值班流程是什么？`
3. 点击 `运行工作流`。
4. 在“执行结果”中查看 `trace/context/output`。

---

### 第6步：接入 AI定时任务（30秒）
1. 左侧切到：`AI大脑 -> AI定时任务`。
2. 新建任务填写：
- 名称：`daily_report`
- Cron：`0 9 * * *`
- 适配器：如 `tgbot`
- 目标类型：`group` 或 `user`
- 目标ID：对应群号/用户ID
- 工作流ID：`demo_kb_answer`
- 固定 Prompt：`请生成今日简报`
3. 保存后可点“运行”立即验证推送。

---

### 快速排错（建议收藏）

- 运行报 `next 不存在`：检查节点 ID 是否拼写一致。
- 运行报 `未找到可用模型`：去配置中心确认有“已启用模型”。
- 条件不生效：先把表达式改成 `kb != []` 这类简单表达式验证。
- 定时任务不推送：检查 `适配器启用状态 + target_id + 任务是否启用`。

---

### 3分钟结果验收标准

满足以下3条就算成功：
1. 工作流能在“运行调试”返回文本输出。
2. 能通过条件分支切到 `l1` 或 `l2`。
3. AI定时任务可手动运行并成功推送到目标。

---

# 插件支持 Webhook（中文指南）

B-BOT 已支持插件级 Webhook 路由，固定格式：

```text
/api/plugins/<plugin_name>/webhook
```

例如插件文件是 `plugins/my_webhook.py`，那么地址就是：

```text
/api/plugins/my_webhook/webhook
```

## 1. 插件函数约定

插件中实现函数 `handle_webhook(...)` 即可被调用，建议签名如下：

```python
def handle_webhook(
    data=None,
    request_method="POST",
    request_headers=None,
    request_content_type="",
    request_path="",
    request_query=None,
    raw_body="",
):
    ...
```

说明：
- `data`: 框架自动解析后的请求数据（优先 JSON，其次 form，再次 query/raw）
- `request_headers`: 请求头字典
- `request_query`: URL query 参数字典
- `raw_body`: 原始 body 文本

## 2. 返回值规范

`handle_webhook` 支持以下返回：
- 返回 `("文本", 状态码)`，例如 `("success", 200)`
- 返回 `(dict, 状态码)`，框架会自动转 JSON
- 仅返回 `dict`，默认状态码 200
- 返回 `None`，框架默认返回 `{"code":200,"msg":"ok"}`

## 3. 最小可用示例（建议先用这个验证）

```python
# plugins/demo_webhook.py
__description__ = "Webhook示例插件"
__version__ = "1.0.0"
__author__ = "B-BOT"


def handle_webhook(
    data=None,
    request_method="POST",
    request_headers=None,
    request_content_type="",
    request_path="",
    request_query=None,
    raw_body="",
):
    payload = dict(data or {})
    return {
        "success": True,
        "message": "Webhook received",
        "method": request_method,
        "path": request_path,
        "payload": payload,
    }
```

测试：

```bash
curl -X POST "http://127.0.0.1:5000/api/plugins/demo_webhook/webhook" \
  -H "Content-Type: application/json" \
  -d '{"event":"ping","ts":123}'
```

## 4. 签名校验示例（生产建议）

```python
# plugins/secure_webhook.py
import hashlib

__description__ = "带签名校验的Webhook示例"
__version__ = "1.0.0"
__author__ = "B-BOT"


def _md5(s: str) -> str:
    return hashlib.md5(s.encode("utf-8")).hexdigest()


def handle_webhook(
    data=None,
    request_method="POST",
    request_headers=None,
    request_content_type="",
    request_path="",
    request_query=None,
    raw_body="",
):
    payload = dict(data or {})

    # 你的固定密钥（建议改为从 bucket 配置读取）
    secret = "replace_with_your_secret"

    received_sign = str(payload.get("sign", "") or "")
    if not received_sign:
        return {"success": False, "message": "missing sign"}, 400

    # 示例签名规则：对 raw_body + secret 做 md5
    expected_sign = _md5((raw_body or "") + secret)
    if received_sign.lower() != expected_sign.lower():
        return {"success": False, "message": "bad sign"}, 403

    # 业务处理
    return {"success": True, "message": "ok"}, 200
```

## 5. 实战建议

- Webhook 插件尽量写成“幂等”：同一个回调重复到达时不重复执行关键动作。
- 强烈建议做签名校验、时间戳校验、IP 白名单（至少做前两项）。
- 外部请求必须加超时，避免阻塞。
- 若要持久化数据，优先使用 `bucket` 存储。



# 内置支付系统接入指南

## 概述

内置支付系统（alipay_pay）已集成到 Middleware 中，插件可直接通过 `middleware` 对象调用支付功能，无需导入任何支付模块。

## 配置方式

在「码支付 → 支付配置」页面填写：
- AppId
- 应用私钥
- 支付宝公钥
- 商户ID
- 商户密钥

---

## Middleware API

### create_payment — 创建订单

```python
result = await middleware.create_payment(money, name, out_trade_no, **kwargs)
```

**参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| money | str | 是 | 金额，保留两位小数，如 `"0.01"` |
| name | str | 是 | 商品名称 |
| out_trade_no | str | 是 | 商户订单号（需唯一） |
| notify_url | str | 否 | 支付回调地址（见下方说明） |
| return_url | str | 否 | 支付完成后跳转地址 |

**返回值 — 传统模式（成功）：**

```json
{
    "code": 1,
    "msg": "SUCCESS",
    "pid": "商户ID",
    "trade_no": "20260516120000123456",
    "out_trade_no": "ORDER_001",
    "money": "0.01",
    "payment_amount": "0.01",
    "payment_url": "https://qr.alipay.com/baxxxxx",
    "qr_code": "base64编码的二维码图片"
}
```

**返回值 — 经营码模式（成功）：**

```json
{
    "code": 1,
    "msg": "SUCCESS",
    "pid": "商户ID",
    "trade_no": "20260516120000123456",
    "out_trade_no": "ORDER_001",
    "money": "0.01",
    "payment_amount": "0.02",
    "payment_url": "经营码收款模式",
    "qr_code_url": "/api/codepay/qrcode?token=xxx",
    "qr_code": "base64编码的经营码图片",
    "business_qr_mode": true,
    "payment_instruction": "请使用支付宝扫描二维码，支付金额：0.02 元",
    "amount_adjusted": true,
    "adjustment_note": "检测到相同金额订单，实际支付金额已调整为 0.02 元"
}
```

**返回值 — 失败：**

```json
{
    "code": -1,
    "msg": "错误信息"
}
```

**返回字段说明：**

| 字段 | 说明 |
|------|------|
| code | 1=成功，-1=失败 |
| trade_no | 内部交易号（系统生成） |
| out_trade_no | 商户订单号（你传入的） |
| money | 原始金额 |
| payment_amount | 实际支付金额（经营码模式可能调整） |
| payment_url | 支付链接（传统模式为支付宝链接，经营码模式为固定文本） |
| qr_code | 二维码图片的 base64 编码（可直接用于 `<img src="data:image/png;base64,...">`） |
| qr_code_url | 经营码二维码图片的 API 路径 |
| business_qr_mode | 是否为经营码模式 |
| payment_instruction | 支付说明文字 |
| amount_adjusted | 经营码模式下金额是否被调整（仅当为 true 时存在） |

---

### query_order — 查询订单

```python
result = await middleware.query_order(out_trade_no)
```

**返回值：**

```json
{
    "code": 1,
    "msg": "SUCCESS",
    "trade_no": "20260516120000123456",
    "out_trade_no": "ORDER_001",
    "type": "alipay",
    "pid": "商户ID",
    "addtime": "2026-05-16 12:00:00",
    "endtime": null,
    "name": "商品名",
    "money": "0.01",
    "status": 0
}
```

**订单状态：**

| status | 说明 |
|--------|------|
| 0 | 待支付 |
| 1 | 已支付 |
| 2 | 已过期 |

---

### process_payment_notification — 处理支付回调

```python
result = await middleware.process_payment_notification(payload)
```

用于处理外部系统推送的支付通知。返回 `{"code": 1, "msg": "SUCCESS"}` 表示处理成功。

---

### get_payment_config — 获取支付配置

```python
config = await middleware.get_payment_config()
```

返回当前支付配置的完整字典，包含 app_id、merchant_id、经营码配置等。

---

## 回调地址（notify_url）

### 说明

`notify_url` 是支付成功后，由外部支付网关主动推送通知的目标地址。**内置支付系统默认不需要配置回调地址**，因为系统通过自动轮询支付宝账单来检测支付状态。

### 何时需要回调地址

| 场景 | 是否需要 notify_url |
|------|---------------------|
| 内置支付系统（本框架） | 不需要，轮询自动检测 |
| 外部 PHP 码支付网关 | 需要，由网关推送通知 |
| 对接第三方支付平台 | 需要，平台推送通知 |

### 回调地址格式

```
http://你的域名:端口/api/codepay?act=notify
```

或使用插件 webhook 地址：

```
http://你的域名:端口/api/plugins/插件名/webhook
```

### 回调参数

支付成功后，回调地址会收到以下 POST 参数：

| 参数 | 说明 |
|------|------|
| pid | 商户ID |
| trade_no | 内部交易号 |
| out_trade_no | 商户订单号 |
| type | 支付方式（alipay） |
| name | 商品名称 |
| money | 金额 |
| trade_status | 交易状态（TRADE_SUCCESS=成功） |
| sign | MD5 签名 |
| sign_type | 签名类型（MD5） |

### 签名验证

回调签名规则与创建订单相同：过滤空值和 sign/sign_type，按键排序后 `md5(拼接字符串 + 商户密钥)`。

### 回调响应

处理成功后需返回纯文本 `success`，否则支付系统会重试通知（最多3次）。

### 内置回调处理

**方式一：通过 API 端点**

```
POST /api/codepay
Content-Type: application/x-www-form-urlencoded

act=notify&pid=xxx&trade_no=xxx&out_trade_no=xxx&trade_status=TRADE_SUCCESS&sign=xxx
```

**方式二：通过插件 webhook**

```
POST /api/plugins/alipay_codepay/webhook
Content-Type: application/x-www-form-urlencoded

pid=xxx&trade_no=xxx&out_trade_no=xxx&trade_status=TRADE_SUCCESS&sign=xxx
```

**方式三：在插件中手动处理**

```python
result = await middleware.process_payment_notification(payload)
if result.get("code") == 1:
    # 支付成功，执行业务逻辑
    pass
```

---

## API 端点

| 端点 | 方法 | 说明 |
|------|------|------|
| `/api/codepay?act=create` | POST | 创建订单（CodePay 协议） |
| `/api/codepay?act=order` | GET | 查询订单状态 |
| `/api/codepay?act=notify` | POST | 支付回调通知 |
| `/api/codepay?act=query` | GET | 查询商户信息 |
| `/api/codepay/status/{out_trade_no}` | GET | 前端轮询订单状态（自动触发监控） |
| `/api/codepay/qrcode` | GET | 获取经营码二维码图片 |
| `/api/alipay/health` | GET | 支付系统健康检查 |
| `/api/alipay/config` | GET/POST | 获取/保存支付配置 |
| `/api/alipay/orders` | GET | 订单列表（分页） |
| `/api/alipay/stats` | GET | 订单统计 |

---

## 判断支付成功

### 方式一：查询订单状态

```python
result = await middleware.query_order("ORDER_001")
if result.get("code") == 1 and result.get("status") == 1:
    print("支付成功")
```

### 方式二：前端轮询接口（推荐）

```javascript
const response = await fetch(`/api/codepay/status/${outTradeNo}`);
const data = await response.json();
if (data.status === 1) {
    // 支付成功
}
```

前端轮询时，系统会自动触发一次支付监控周期，加速支付状态检测。

### 方式三：自行实现轮询

```python
import asyncio

async def wait_for_payment(out_trade_no, timeout=300):
    elapsed = 0
    while elapsed < timeout:
        result = await middleware.query_order(out_trade_no)
        if result.get("code") == 1 and result.get("status") == 1:
            return True
        await asyncio.sleep(3)
        elapsed += 3
    return False
```

### 方式四：回调通知

配置 `notify_url`，支付成功后系统会主动推送通知到该地址（适合外部系统对接）。

---

## 支付流程

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  创建订单   │ ──▶ │  显示二维码  │ ──▶ │  用户扫码   │
└─────────────┘     └─────────────┘     └─────────────┘
                                              │
                                              ▼
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  支付成功   │ ◀── │  金额匹配   │ ◀── │  账单查询   │
└─────────────┘     └─────────────┘     └─────────────┘
       │
       ├──── 轮询获取最新状态（前端/插件）
       │
       └──── 回调通知（notify_url，可选）
```

---

## 机器人命令

框架内置了 `alipay_codepay` 插件，支持以下机器人命令：

- `创建支付 <商品名> <金额>` — 创建订单并返回二维码
- `查询订单 <订单号>` — 查询订单状态
- `支付状态` — 查看待支付订单

---

## 注意事项

1. **订单超时**：默认 300 秒后订单自动过期，可在支付配置页面修改
2. **金额精度**：金额保留两位小数
3. **经营码模式**：开启后自动分配唯一金额，避免重复匹配
4. **二维码图片**：`create_payment` 返回的 `qr_code` 是 base64 编码，可直接用于图片显示

---

## 完整示例

```python
async def my_payment_flow():
    """完整的支付流程示例"""

    # 1. 创建订单
    out_trade_no = "ORDER_001"
    result = await middleware.create_payment("9.99", "VIP会员", out_trade_no)

    if result.get("code") != 1:
        return {"success": False, "error": result.get("msg")}

    # 获取二维码（base64，可直接发给用户）
    qr_code = result.get("qr_code", "")

    # 2. 等待支付（自行轮询或使用前端轮询接口）
    import asyncio
    for _ in range(40):  # 最多等2分钟
        await asyncio.sleep(3)
        status_result = await middleware.query_order(out_trade_no)
        if status_result.get("code") == 1 and status_result.get("status") == 1:
            # 3. 支付成功，执行业务逻辑
            await middleware.push_to_user("qq", "123456", f"支付成功: {out_trade_no}")
            return {"success": True, "order": out_trade_no}

    return {"success": False, "error": "支付超时"}
```
