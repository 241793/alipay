<?php

namespace AliMPay\Utils;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use AliMPay\Utils\Logger;

class QRCodeGenerator
{
    private $logger;
    
    public function __construct()
    {
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Check if GD extension is available
     * 
     * @return bool
     */
    private function isGDAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreate');
    }
    
    /**
     * Generate QR code for transfer link
     * 
     * @param string $transferUrl Transfer URL
     * @param string $savePath File save path
     * @param int $size QR code size
     * @return string Generated QR code file path
     */
    public function generateQRCode(string $transferUrl, string $savePath = null, int $size = 300): string
    {
        try {
            // Create QR codes directory if it doesn't exist
            $qrCodeDir = __DIR__ . '/../../qrcodes';
            if (!is_dir($qrCodeDir)) {
                mkdir($qrCodeDir, 0755, true);
            }
            
            // Generate file path if not provided
            if (!$savePath) {
                $fileName = 'qrcode_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.png';
                $savePath = $qrCodeDir . '/' . $fileName;
            }
            
            // Try to use local QR code generation first
            if ($this->isGDAvailable()) {
                return $this->generateQRCodeLocal($transferUrl, $savePath, $size);
            } else {
                // Fallback to online QR code generation
                $this->logger->warning('GD extension not available, using online QR code generation');
                return $this->generateQRCodeOnline($transferUrl, $savePath, $size);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate QR code', [
                'error' => $e->getMessage(),
                'url' => $transferUrl
            ]);
            
            // Try online generation as fallback
            try {
                $this->logger->info('Trying online QR code generation as fallback');
                return $this->generateQRCodeOnline($transferUrl, $savePath, $size);
            } catch (\Exception $onlineError) {
                throw new \Exception('QR code generation failed: ' . $e->getMessage() . ' (Online fallback also failed: ' . $onlineError->getMessage() . ')');
            }
        }
    }
    
    /**
     * Generate QR code using local library
     * 
     * @param string $transferUrl
     * @param string $savePath
     * @param int $size
     * @return string
     */
    private function generateQRCodeLocal(string $transferUrl, string $savePath, int $size): string
    {
        // Generate QR code using endroid/qr-code
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($transferUrl)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size($size)
            ->margin(10)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->build();
        
        // Save QR code
        $result->saveToFile($savePath);
        
        $this->logger->info('QR code generated successfully (local)', [
            'url' => $transferUrl,
            'file_path' => $savePath,
            'size' => $size
        ]);
        
        return $savePath;
    }
    
    /**
     * Generate QR code using online API
     * 
     * @param string $transferUrl
     * @param string $savePath
     * @param int $size
     * @return string
     */
    private function generateQRCodeOnline(string $transferUrl, string $savePath, int $size): string
    {
        // URL encode the transfer URL
        $encodedUrl = urlencode($transferUrl);
        
        // Use QR Server API (free service)
        $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedUrl}";
        
        // Download QR code image
        $qrImageData = file_get_contents($qrApiUrl);
        
        if ($qrImageData === false) {
            throw new \Exception('Failed to download QR code from online service');
        }
        
        // Save QR code to file
        if (file_put_contents($savePath, $qrImageData) === false) {
            throw new \Exception('Failed to save QR code to file');
        }
        
        $this->logger->info('QR code generated successfully (online)', [
            'url' => $transferUrl,
            'file_path' => $savePath,
            'size' => $size,
            'api_url' => $qrApiUrl
        ]);
        
        return $savePath;
    }
    
    /**
     * Generate base64 encoded QR code
     * 
     * @param string $transferUrl
     * @param int $size
     * @return string Base64 encoded QR code
     */
    public function generateQRCodeBase64(string $transferUrl, int $size = 300): string
    {
        try {
            // Try local generation first
            if ($this->isGDAvailable()) {
                $result = Builder::create()
                    ->writer(new PngWriter())
                    ->data($transferUrl)
                    ->encoding(new Encoding('UTF-8'))
                    ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
                    ->size($size)
                    ->margin(10)
                    ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                    ->build();
                
                return base64_encode($result->getString());
            } else {
                // Use online API for base64
                $encodedUrl = urlencode($transferUrl);
                $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedUrl}";
                
                $qrImageData = file_get_contents($qrApiUrl);
                
                if ($qrImageData === false) {
                    throw new \Exception('Failed to download QR code from online service');
                }
                
                return base64_encode($qrImageData);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate QR code base64', [
                'error' => $e->getMessage(),
                'url' => $transferUrl
            ]);
            
            throw new \Exception('QR code generation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate QR code URL (online only)
     * 
     * @param string $transferUrl
     * @param int $size
     * @return string QR code URL
     */
    public function generateQRCodeUrl(string $transferUrl, int $size = 300): string
    {
        $encodedUrl = urlencode($transferUrl);
        $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedUrl}";
        
        $this->logger->info('QR code URL generated', [
            'url' => $transferUrl,
            'qr_url' => $qrApiUrl,
            'size' => $size
        ]);
        
        return $qrApiUrl;
    }

    /**
     * 生成二维码图片的base64字符串
     * @param string $text
     * @return string
     */
    public function generate(string $text): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($text)
            ->size(200)
            ->margin(10)
            ->build();

        // 获取二维码图片内容并转为base64
        $data = $result->getString();
        return base64_encode($data);
    }
}