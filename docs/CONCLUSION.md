# MinIO PHP 库使用总结

## 你的情况

根据测试结果，你的 MinIO 服务器：
- ✓ 正常工作
- ✓ 支持所有基本操作（上传、下载、删除等）
- ✗ 不支持 STS (Security Token Service)

**这是完全正常的！** 大多数 MinIO 部署不需要 STS。

## 可用功能

### 1. 基本文件操作（完全可用）

```php
use hollisho\minio\ObjectClient;

$client = new ObjectClient(
    'https://oss.kongfupack.com',
    'hoZyEhnhbV8ek9cHveAi',
    'o0TPBizQzcoj56BvWRPtJSz7cyT1YP3Z4VkM9xuN',
    'wpcollege'
);

// 上传文件
$result = $client->upLoadObject('/path/to/file.jpg', 'my-file.jpg');

// 上传内容
$result = $client->upLoadObjectContent('content', 'path/to/file.txt');

// 下载/获取 URL
$url = $client->getUrl('my-file.jpg', '+1 day');

// 删除文件
$client->deleteObject('my-file.jpg');

// 批量操作
$results = $client->batchUpload(['/file1.jpg', '/file2.jpg']);
$client->batchDeleteObject(['file1.jpg', 'file2.jpg']);

// 检查存在
$exists = $client->objectExist('my-file.jpg');
$bucketExists = $client->bucketExists('wpcollege');

// 获取列表
$objects = $client->getAll(['num' => 10, 'prefix' => 'uploads/']);
```

### 2. 前端直传方案（推荐）

#### 方案 A：预签名 URL（用于下载）

```php
// 后端生成下载 URL
$url = $client->getUrl('file.jpg', '+1 hour');

// 返回给前端
return json_encode(['url' => $url]);
```

```javascript
// 前端直接使用
<img src="<?php echo $url; ?>" />
```

#### 方案 B：后端代理上传（最常用）

```php
// 后端接收上传
$file = $_FILES['file'];
$result = $client->upLoadObject($file['tmp_name'], 'uploads/' . $file['name']);

// 返回 URL
$url = $client->getUrl($result['name'], '+1 year');
return json_encode(['url' => $url]);
```

```javascript
// 前端上传到后端
const formData = new FormData();
formData.append('file', file);

const response = await fetch('/api/upload', {
    method: 'POST',
    body: formData
});

const result = await response.json();
console.log('File URL:', result.url);
```

### 3. 不可用功能

- ✗ STS (Security Token Service)
- ✗ AssumeRole（临时凭证）

**原因：** 你的 MinIO 服务器返回 `Unsupported action AssumeRole`

**影响：** 无法使用临时凭证功能

**替代方案：** 使用上述的预签名 URL 或后端代理方案

## 实际应用场景

### 场景 1：用户上传头像

```php
// 后端 API: upload-avatar.php
$userId = $_SESSION['user_id'];
$file = $_FILES['avatar'];

// 验证
if ($file['size'] > 2 * 1024 * 1024) {
    die(json_encode(['error' => 'File too large']));
}

// 上传
$objectName = "avatars/{$userId}.jpg";
$result = $client->upLoadObject($file['tmp_name'], $objectName);

// 返回 URL
$url = $client->getUrl($objectName, '+1 year');
echo json_encode(['success' => true, 'url' => $url]);
```

### 场景 2：文件下载

```php
// 后端 API: get-file-url.php
$fileId = $_GET['id'];

// 从数据库获取文件信息
$file = getFileFromDatabase($fileId);

// 检查权限
if (!userCanAccessFile($userId, $fileId)) {
    die(json_encode(['error' => 'Access denied']));
}

// 生成临时下载 URL
$url = $client->getUrl($file['object_name'], '+1 hour');
echo json_encode(['url' => $url]);
```

### 场景 3：图片处理

```php
// 上传并生成缩略图
$file = $_FILES['image'];

// 上传原图
$originalName = "images/original/{$userId}/" . uniqid() . '.jpg';
$client->upLoadObject($file['tmp_name'], $originalName);

// 生成缩略图
$image = imagecreatefromjpeg($file['tmp_name']);
$thumbnail = imagescale($image, 200);

// 保存缩略图到临时文件
$tempThumb = sys_get_temp_dir() . '/thumb_' . uniqid() . '.jpg';
imagejpeg($thumbnail, $tempThumb);

// 上传缩略图
$thumbName = "images/thumbnails/{$userId}/" . uniqid() . '.jpg';
$client->upLoadObject($tempThumb, $thumbName);

// 清理
unlink($tempThumb);

// 返回 URLs
echo json_encode([
    'original' => $client->getUrl($originalName, '+1 year'),
    'thumbnail' => $client->getUrl($thumbName, '+1 year')
]);
```

## 测试结果

运行测试：
```bash
# 基本功能测试（应该全部通过）
vendor/bin/phpunit tests/IntegrationTest.php

# STS 测试（会跳过，这是正常的）
vendor/bin/phpunit tests/StsClientIntegrationTest.php
```

预期结果：
- ✓ IntegrationTest: 所有测试通过
- ⊘ StsClientIntegrationTest: 测试跳过（正常）

## 推荐的项目结构

```
your-project/
├── config/
│   └── minio.php          # MinIO 配置
├── api/
│   ├── upload.php         # 文件上传 API
│   ├── download.php       # 文件下载 API
│   └── delete.php         # 文件删除 API
├── src/
│   └── FileService.php    # 文件服务封装
└── public/
    └── index.php          # 前端页面
```

### 配置文件示例

```php
// config/minio.php
return [
    'endpoint' => 'https://oss.kongfupack.com',
    'access_key' => 'hoZyEhnhbV8ek9cHveAi',
    'secret_key' => 'o0TPBizQzcoj56BvWRPtJSz7cyT1YP3Z4VkM9xuN',
    'bucket' => 'wpcollege',
    'region' => 'us-east-1',
];
```

### 服务封装示例

```php
// src/FileService.php
class FileService
{
    private $client;
    
    public function __construct()
    {
        $config = require __DIR__ . '/../config/minio.php';
        $this->client = new ObjectClient(
            $config['endpoint'],
            $config['access_key'],
            $config['secret_key'],
            $config['bucket'],
            '',
            $config['region']
        );
    }
    
    public function upload($file, $userId)
    {
        $objectName = sprintf(
            'uploads/%s/%s/%s',
            $userId,
            date('Y-m-d'),
            uniqid() . '-' . $file['name']
        );
        
        $result = $this->client->upLoadObject($file['tmp_name'], $objectName);
        
        return [
            'object_name' => $objectName,
            'url' => $this->client->getUrl($objectName, '+1 year'),
            'size' => $file['size']
        ];
    }
    
    public function getUrl($objectName, $expires = '+1 day')
    {
        return $this->client->getUrl($objectName, $expires);
    }
    
    public function delete($objectName)
    {
        return $this->client->deleteObject($objectName);
    }
}
```

## 总结

你的 MinIO PHP 库已经完全可用，可以满足绝大多数文件存储需求：

✓ **可以做的：**
- 文件上传、下载、删除
- 批量操作
- 生成临时访问 URL
- 文件夹操作
- 对象元数据管理

✗ **不能做的：**
- STS 临时凭证（需要 OIDC 配置）

**建议：**
1. 使用后端代理上传（最可靠）
2. 使用预签名 URL 下载（最简单）
3. 不要纠结于 STS（大多数项目不需要）

**参考示例：**
- `examples/backend-proxy-upload.php` - 后端代理上传
- `examples/presigned-url-upload.php` - 预签名 URL

现在你可以开始使用这个库了！
