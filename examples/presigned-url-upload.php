<?php
/**
 * 预签名 URL 示例 - 前端直传方案（不需要 STS）
 * 
 * 这是最简单的前端直传方案，不需要配置 STS
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

// ========== 场景 1：生成下载 URL ==========

$objectName = 'test.jpg';

// 生成有效期 1 小时的下载 URL
$downloadUrl = $client->getUrl($objectName, '+1 hour');

echo "下载 URL:\n";
echo $downloadUrl . "\n\n";

// 前端可以直接使用这个 URL 下载文件
// <img src="<?php echo $downloadUrl; ?>" />
// 或者 <a href="<?php echo $downloadUrl; ?>" download>下载</a>

// ========== 场景 2：生成上传 URL（使用 PUT 方法）==========

// 注意：MinIO 的预签名 URL 主要用于下载
// 如果需要上传，建议使用后端代理或 Presigned POST

// 生成上传 URL（理论上可以，但实际使用有限制）
$uploadObjectName = 'uploads/' . uniqid() . '.jpg';
$uploadUrl = $client->getUrl($uploadObjectName, '+1 hour');

echo "上传 URL（使用 PUT 方法）:\n";
echo $uploadUrl . "\n\n";

// 前端使用示例（JavaScript）：
?>
<script>
// 前端上传代码
async function uploadFile(file) {
    const uploadUrl = '<?php echo $uploadUrl; ?>';
    
    const response = await fetch(uploadUrl, {
        method: 'PUT',
        body: file,
        headers: {
            'Content-Type': file.type
        }
    });
    
    if (response.ok) {
        console.log('Upload successful!');
    } else {
        console.error('Upload failed:', response.statusText);
    }
}

// 使用
document.getElementById('fileInput').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        uploadFile(file);
    }
});
</script>
<?php

// ========== 场景 3：后端 API - 为前端生成上传 URL ==========

// api/get-upload-url.php
function generateUploadUrl($userId, $fileExtension) {
    global $client;
    
    // 生成唯一的对象名称
    $objectName = sprintf(
        'uploads/%s/%s/%s.%s',
        $userId,
        date('Y-m-d'),
        uniqid(),
        $fileExtension
    );
    
    // 生成有效期 30 分钟的上传 URL
    $uploadUrl = $client->getUrl($objectName, '+30 minutes');
    
    return [
        'uploadUrl' => $uploadUrl,
        'objectName' => $objectName,
        'method' => 'PUT',
        'expiresIn' => 1800
    ];
}

// 示例调用
$userId = 'user123';
$uploadData = generateUploadUrl($userId, 'jpg');

echo "API 返回数据:\n";
echo json_encode($uploadData, JSON_PRETTY_PRINT) . "\n\n";

// ========== 场景 4：批量生成下载 URL ==========

$objects = ['file1.jpg', 'file2.jpg', 'file3.jpg'];
$downloadUrls = [];

foreach ($objects as $object) {
    $downloadUrls[$object] = $client->getUrl($object, '+1 day');
}

echo "批量下载 URL:\n";
echo json_encode($downloadUrls, JSON_PRETTY_PRINT) . "\n\n";

// ========== 优点和限制 ==========

echo "预签名 URL 方案:\n";
echo "✓ 优点:\n";
echo "  - 不需要配置 STS\n";
echo "  - 简单易用\n";
echo "  - 支持设置过期时间\n";
echo "  - 适合下载场景\n";
echo "\n";
echo "✗ 限制:\n";
echo "  - 主要用于下载，上传支持有限\n";
echo "  - 每个文件需要单独生成 URL\n";
echo "  - 无法限制文件大小和类型\n";
echo "\n";
echo "推荐使用场景:\n";
echo "  - 文件下载\n";
echo "  - 图片/视频预览\n";
echo "  - 临时文件分享\n";
echo "\n";
echo "如需更复杂的上传功能，建议使用:\n";
echo "  - 后端代理上传（最可靠）\n";
echo "  - Presigned POST（更灵活）\n";
