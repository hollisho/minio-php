# Minio PHP

## Install

Via Composer

``` bash
$ composer require hollisho/minio-php
```


## Usage

### ObjectClient 示例

```php
use hollisho\minio\ObjectClient;

// 创建客户端
$client = new ObjectClient(
    'https://oss.kongfupack.com',  // endpoint
    'your-access-key',              // access key
    'your-secret-key',              // secret key
    'your-bucket'                   // bucket name
);

// 或使用单例模式
$client = ObjectClient::getInstance(
    'https://oss.kongfupack.com',
    'your-access-key',
    'your-secret-key',
    'your-bucket'
);

// 上传文件
$result = $client->upLoadObject('/path/to/file.jpg', 'my-file.jpg');

// 上传内容
$result = $client->upLoadObjectContent('file content', 'path/to/file.txt');

// 获取文件 URL
$url = $client->getUrl('my-file.jpg', '+1 day');

// 删除文件
$client->deleteObject('my-file.jpg');

// 批量上传
$files = ['/path/to/file1.jpg', '/path/to/file2.jpg'];
$results = $client->batchUpload($files);

// 批量删除
$objects = ['file1.jpg', 'file2.jpg'];
$client->batchDeleteObject($objects);
```

### StsClient 示例

```php
use hollisho\minio\StsClient;
use hollisho\minio\ObjectClient;

// ========== 服务端：生成临时凭证 ==========

// 创建 STS 客户端（使用主凭证）
$stsClient = new StsClient(
    'https://oss.kongfupack.com',
    'your-access-key',
    'your-secret-key',
    'arn:aws:iam::123456789012:role/your-role'
);

// 定义权限策略（只允许上传到 uploads/ 目录）
$policy = json_encode([
    'Version' => '2012-10-17',
    'Statement' => [
        [
            'Effect' => 'Allow',
            'Action' => ['s3:PutObject', 's3:GetObject'],
            'Resource' => ['arn:aws:s3:::your-bucket/uploads/*']
        ]
    ]
]);

// 获取临时凭证（有效期 1 小时）
$tempCredentials = $stsClient->assumeRole(
    3600,                           // 有效期（秒）
    $policy,                        // 权限策略
    'client-session-' . uniqid()    // 会话名称
);

// 返回给客户端的临时凭证
$credentialsForClient = [
    'accessKeyId' => $tempCredentials->getAccessKeyId(),
    'secretAccessKey' => $tempCredentials->getSecretKey(),
    'sessionToken' => $tempCredentials->getSecurityToken(),
    'expiresIn' => 3600
];

// ========== 客户端：使用临时凭证 ==========

// 客户端使用临时凭证创建 ObjectClient
$client = new ObjectClient(
    'https://oss.kongfupack.com',
    $credentialsForClient['accessKeyId'],
    $credentialsForClient['secretAccessKey'],
    'your-bucket',
    $credentialsForClient['sessionToken']  // 临时凭证的 token
);

// 客户端可以在权限范围内进行操作
$client->upLoadObject('/path/to/file.jpg', 'uploads/file.jpg');
```

## Testing

查看详细测试说明：[tests/README.md](tests/README.md)

### 快速开始

```bash
# 安装依赖
composer install

# 运行单元测试
vendor/bin/phpunit --exclude-group integration

# 运行集成测试（需要配置 MinIO 连接信息）
vendor/bin/phpunit tests/IntegrationTest.php

# 运行 STS 测试（需要 MinIO 配置 STS，否则会跳过）
vendor/bin/phpunit tests/StsClientIntegrationTest.php
```

### 关于 STS 测试

STS (Security Token Service) 是可选功能，大多数应用不需要。如果你看到 STS 测试被跳过，这是**正常的**。

**为什么 STS 测试失败？**
- MinIO 的 STS 功能需要配置 OIDC（OpenID Connect）才能启用
- 配置 OIDC 需要额外的身份提供者服务器（Keycloak、Auth0 等）
- 配置复杂，大多数项目不需要

**推荐的替代方案（立即可用）：**
1. **后端代理上传**（最推荐）- 见 `examples/backend-proxy-upload.php`
2. **预签名 URL**（最简单）- 见 `examples/presigned-url-upload.php`

**相关文档：**
- [最终建议](docs/FINAL_RECOMMENDATION.md) - 你应该使用哪种方案
- [STS 故障排查](docs/STS_TROUBLESHOOTING.md) - 为什么 STS 不工作
- [启用 STS](docs/ENABLE_STS.md) - 如果确实需要配置 STS