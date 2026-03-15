<?php

namespace AliMPay\Utils;

class PushPlusNotifier
{
    private $config;
    private $logger;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config['pushplus'] ?? [];
        $this->logger = $logger;
    }

    public function sendPaymentSuccess(array $orderData, string $detectedBy = 'unknown'): bool
    {
        $enabled = (bool)($this->config['enabled'] ?? false);
        $token = trim((string)($this->config['token'] ?? ''));

        if (!$enabled) {
            return false;
        }

        if ($token === '') {
            $this->logger->warning('PushPlus is enabled but token is empty.');
            return false;
        }

        $endpoint = (string)($this->config['endpoint'] ?? 'http://www.pushplus.plus/send');
        $template = (string)($this->config['template'] ?? 'markdown');
        $channel = (string)($this->config['channel'] ?? '');
        $topic = (string)($this->config['topic'] ?? '');

        $amount = isset($orderData['price']) ? number_format((float)$orderData['price'], 2, '.', '') : '';
        $payTime = $orderData['pay_time'] ?? date('Y-m-d H:i:s');
        $titlePrefix = (string)($this->config['title_prefix'] ?? 'AliMPay');
        $title = "{$titlePrefix} 支付成功通知";

        $content = [];
        $content[] = "### 订单支付成功";
        $content[] = "";
        $content[] = "- 订单号: " . ($orderData['out_trade_no'] ?? '');
        $content[] = "- 内部单号: " . ($orderData['id'] ?? '');
        $content[] = "- 金额: " . $amount;
        $content[] = "- 商品: " . ($orderData['name'] ?? '');
        $content[] = "- 支付时间: " . $payTime;
        $content[] = "- 检测渠道: " . $detectedBy;
        $contentText = implode("\n", $content);

        $payload = [
            'token' => $token,
            'title' => $title,
            'content' => $contentText,
            'template' => $template
        ];

        if ($channel !== '') {
            $payload['channel'] = $channel;
        }
        if ($topic !== '') {
            $payload['topic'] = $topic;
        }

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'timeout' => 8
                ]
            ]);

            $response = file_get_contents($endpoint, false, $context);
            if ($response === false) {
                $this->logger->warning('PushPlus request failed (empty response).', ['endpoint' => $endpoint]);
                return false;
            }

            $decoded = json_decode($response, true);
            $ok = is_array($decoded) && (int)($decoded['code'] ?? -1) === 200;

            if ($ok) {
                $this->logger->info('PushPlus notification sent successfully.', [
                    'out_trade_no' => $orderData['out_trade_no'] ?? '',
                    'detected_by' => $detectedBy
                ]);
            } else {
                $this->logger->warning('PushPlus notification failed.', [
                    'out_trade_no' => $orderData['out_trade_no'] ?? '',
                    'response' => $response
                ]);
            }

            return $ok;
        } catch (\Exception $e) {
            $this->logger->error('PushPlus notification exception.', [
                'error' => $e->getMessage(),
                'out_trade_no' => $orderData['out_trade_no'] ?? ''
            ]);
            return false;
        }
    }
}
