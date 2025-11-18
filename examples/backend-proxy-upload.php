<?php
/**
 * 后端代理上传示例 - 最常用、最可靠的方案
 * 
 * 流程：前端 → 后端 API → MinIO
 * 优点：完全控制、可验证、可处理
 */

require_once __DIR__ . '/../vendor/autoload.php';

use hollisho\minio\ObjectClient;

// 配置
$endpoint = 'https://oss.kongfupack.com';
$accessKey = 'hoZyEhnhbV8ek9cHveAi';
$secretKey = 'o0TPBizQzcoj56BvWRPtJSz7cyT1YP3Z4VkM9xuN';
$bucket = 'wpcollege';

// 创建客户端
$client = new ObjectClient($endpoint, $accessKey, $secretKey, $bucket);

// ========== 场景 1：处理表单上传 ==========

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    
    $file = $_FILES['file'];
    
    // 1. 验证文件
    $maxSize = 10 * 1024 * 1024; // 10MB
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die(json_encode(['error' => 'Upload failed']));
    }
    
    if ($file['size'] > $maxSize) {
        die(json_encode(['error' => 'File too large']));
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        die(json_encode(['error' => 'Invalid file type']));
    }
    
    // 2. 生成对象名称
    $userId = $_SESSION['user_id'] ?? 'guest';
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $objectName = sprintf(
        'uploads/%s/%s/%s.%s',
        $userId,
        date('Y-m-d'),
        uniqid(),
        $extension
    );
    
    // 3. 上传到 MinIO
    try {
        $result = $client->upLoadObject($file['tmp_name'], $objectName);
        
        // 4. 生成访问 URL
        $url = $client->getUrl($objectName, '+1 year');
        
        // 5. 返回结果
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'url' => $url,
            'objectName' => $objectName,
            'size' => $file['size']
        ]);
        
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}

// ========== 场景 2：上传 Base64 图片 ==========

function uploadBase64Image($base64Data, $userId) {
    global $client;
    
    // 解析 Base64
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
        $extension = $matches[1];
        $data = substr($base64Data, strpos($base64Data, ',') + 1);
        $data = base64_decode($data);
        
        // 生成对象名称
        $objectName = sprintf(
            'uploads/%s/%s/%s.%s',
            $userId,
            date('Y-m-d'),
            uniqid(),
            $extension
        );
        
        // 上传
        $result = $client->upLoadObjectContent($data, $objectName);
        
        if ($result) {
            return [
                'success' => true,
                'url' => $client->getUrl($objectName, '+1 year'),
                'objectName' => $objectName
            ];
        }
    }
    
    return ['success' => false, 'error' => 'Invalid base64 data'];
}

// ========== 场景 3：上传远程文件 ==========

function uploadRemoteFile($remoteUrl, $userId) {
    global $client;
    
    // 下载远程文件
    $content = file_get_contents($remoteUrl);
    
    if ($content === false) {
        return ['success' => false, 'error' => 'Failed to download remote file'];
    }
    
    // 获取文件扩展名
    $extension = pathinfo(parse_url($remoteUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
    
    // 生成对象名称
    $objectName = sprintf(
        'uploads/%s/%s/%s.%s',
        $userId,
        date('Y-m-d'),
        uniqid(),
        $extension
    );
    
    // 上传
    $result = $client->upLoadObjectContent($content, $objectName);
    
    if ($result) {
        return [
            'success' => true,
            'url' => $client->getUrl($objectName, '+1 year'),
            'objectName' => $objectName
        ];
    }
    
    return ['success' => false, 'error' => 'Upload failed'];
}

// ========== HTML 表单示例 ==========
?>
<!DOCTYPE html>
<html>
<head>
    <title>文件上传示例</title>
</head>
<body>
    <h1>后端代理上传</h1>
    
    <form id="uploadForm" enctype="multipart/form-data">
        <input type="file" name="file" id="fileInput" accept="image/*,application/pdf">
        <button type="submit">上传</button>
    </form>
    
    <div id="result"></div>
    
    <script>
    document.getElementById('uploadForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData();
        const fileInput = document.getElementById('fileInput');
        formData.append('file', fileInput.files[0]);
        
        try {
            const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('result').innerHTML = 
                    `<p>上传成功！</p>
                     <p>URL: <a href="${result.url}" target="_blank">${result.url}</a></p>
                     <img src="${result.url}" style="max-width: 300px;">`;
            } else {
                document.getElementById('result').innerHTML = 
                    `<p style="color: red;">上传失败: ${result.error}</p>`;
            }
        } catch (error) {
            document.getElementById('result').innerHTML = 
                `<p style="color: red;">错误: ${error.message}</p>`;
        }
    });
    </script>
</body>
</html>
<?php

// ========== 优点和使用场景 ==========

echo "\n后端代理上传方案:\n";
echo "✓ 优点:\n";
echo "  - 完全控制上传过程\n";
echo "  - 可以验证文件（大小、类型、内容）\n";
echo "  - 可以处理文件（压缩、水印、转换）\n";
echo "  - 可以记录日志和统计\n";
echo "  - 最可靠、最安全\n";
echo "\n";
echo "✗ 缺点:\n";
echo "  - 文件需要经过后端服务器\n";
echo "  - 占用后端带宽和资源\n";
echo "  - 大文件上传可能较慢\n";
echo "\n";
echo "推荐使用场景:\n";
echo "  - 需要验证和处理文件\n";
echo "  - 需要记录上传日志\n";
echo "  - 文件大小适中（< 100MB）\n";
echo "  - 安全性要求高\n";
