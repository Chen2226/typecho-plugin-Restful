<?php

namespace TypechoPlugin\Restful;

class Util
{
    /**
     * 获取前端上传文件（兼容多种格式）
     * @throws \Exception
     */
    public static function getUploadFile($files, $request)
    {
        $file = '';
        if (empty($files)) {
            // 兼容前端以 Uint8Array 通过 POST 发送的文件数据
            $exists = '';
            // 1) 尝试从请求参数中取
            $postBytes = $request->get('file', null, $exists);
            $fileNameParam = $request->get('fileName') ?: $request->get('name');

            // 2) 若取不到，则尝试解析原始请求体（常见于 application/json）
            if (!$exists || $postBytes === null) {
                $raw = file_get_contents('php://input');
                if (is_string($raw) && $raw !== '') {
                    $json = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                        if (isset($json['file'])) {
                            $postBytes = $json['file'];
                        }
                        if (!$fileNameParam && isset($json['fileName'])) {
                            $fileNameParam = $json['fileName'];
                        } elseif (!$fileNameParam && isset($json['name'])) {
                            $fileNameParam = $json['name'];
                        }
                    }
                }
            }

            if ($postBytes === null) {
                throw new \Exception('missing file');
            }

            // 3) 处理可能的多种表示形式
            // - 数字数组（Uint8Array）
            // - base64 字符串（data:*;base64,xxx 或纯 base64）
            // - 纯二进制字符串（不推荐，但做兜底）
            $binary = null;
            if (is_array($postBytes)) {
                if (empty($postBytes)) {
                    throw new \Exception('missing file');
                }
                $bytes = array_map(static function ($v) {
                    $v = (int)$v;
                    if ($v < 0) {
                        $v = 0;
                    }
                    if ($v > 255) {
                        $v = 255;
                    }
                    return $v;
                }, $postBytes);
                $binary = pack('C*', ...$bytes);
            } elseif (is_string($postBytes)) {
                // data URL 或 base64
                if (strpos($postBytes, 'base64,') !== false) {
                    $base64 = substr($postBytes, strpos($postBytes, 'base64,') + 7);
                    $binary = base64_decode($base64, true);
                } else {
                    // 尝试按 base64 解码，不合法则当作原始二进制
                    $decoded = base64_decode($postBytes, true);
                    $binary = ($decoded !== false) ? $decoded : $postBytes;
                }
            } else {
                throw new \Exception('missing file');
            }

            $name = $fileNameParam ?: 'upload.bin';
            if ($request->isAjax() && $name) {
                $name = urldecode($name);
            }

            $file = array(
                'name' => $name,
                'bytes' => $binary,
                'size' => strlen($binary),
            );
        } else {
            $file = array_pop($files);
            if (!isset($file['error']) || 0 !== (int)$file['error'] || !is_uploaded_file($file['tmp_name'])) {
                throw new \Exception('upload failed');
            }
            if ($request->isAjax() && isset($file['name'])) {
                $file['name'] = urldecode($file['name']);
            }
        }
        return $file;
    }
}
