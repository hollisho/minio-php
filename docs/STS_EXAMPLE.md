# MinIO STS 完整配置示例

本文档提供一个完整的 MinIO STS 配置示例，从零开始配置到测试成功。

## 环境信息

- MinIO 服务器：https://oss.kongfupack.com
- Bucket：wpcollege
- 目标：允许临时凭证上传文件到 `temp/` 和 `uploads/` 目录

## 步骤 1：安装和配置 MinIO Client

```bash
# 下载 mc
wget https://dl.min.io/client/mc/release/linux-amd64/mc
chmod +x mc
sudo mv mc /usr/local/bin/

# 配置连接
mc alias set myminio https://oss.kongfupack.com hoZyEhnhbV8ek9cHveAi o0TPBizQzcoj56BvWRPtJSz7cyT1YP3Z4VkM9xuN

# 测试连接
mc ls myminio
```

## 步骤 2：创建 IAM 策略

```bash
# 创建策略文件
cat > /tmp/temp-upload-policy.json <<'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::wpcollege/temp/*",
        "arn:aws:s3:::wpcollege/uploads/*"
      ]
    }
  ]
}
EOF

# 创建策略
mc admin policy create myminio temp-upload-policy /tmp/temp-upload-policy.json

# 验证策略已创建
mc admin policy list myminio
mc admin policy info myminio temp-upload-policy
```

## 步骤 3：确定 Role ARN

MinIO 的 Role ARN 通常基于策略名称。尝试以下格式：

```php
// 格式 1（最常用）
$roleArn = 'arn:aws:iam::minio:role/temp-upload-policy';

// 格式 2
$roleArn = 'arn:aws:iam::123456789012:role/temp-upload-policy';

// 格式 3（某些 MinIO 版本）
$roleArn = 'arn:minio:iam:::role/temp-upload-policy';
```

## 步骤 4：在代码中使用

### 4.1 更新测试配置

编辑 `tests/StsClientIntegrationTest.php`：

```php
class StsClientIntegrationTest extends TestCase
{
    private $endpoint = 'https://oss.kongfupack.com';
    private $masterAccessKey = 'hoZyEhnhbV8ek9cHveAi';
    private $masterSecretKey = 'o0TPBizQzcoj56BvWRPtJSz7cyT1YP3Z4VkM9xuN';
    private $bucket = 'wpcollege';
    private $region = 'us-east-1';
    
    // 使用你在 MinIO 中创建的策略名称
    private $roleArn = 'arn:aws:iam::minio:role/temp-upload-policy';
    
    // ...
}
```

### 4.2 运行测试

```bash
vendor/bin/phpunit tests/StsClientIntegrationTest.php --verbose
```

## 步骤 5：实际应用代码

### 后端 API：生成临时凭证

```php
<?php
// api/get-upload-credentials.php

use hollisho\minio\StsClient;

// 创建 STS 客户端
$stsClient = new StsClient(
    'https://oss.kongfupack.com',
    'hoZyEhnhbV8ek9cHveAi',
    'o0TPBizQzcoj56BvWRPtJSz7cyT1YP3Z4VkM9xuN',
    'arn:aws:iam::minio:role/temp-upload-policy',  // 必须与 MinIO 配置一致
    'us-east-1'
);

// 为当前用户生成上传路径
$userId = $_SESSION['user_id'] ?? 'guest';
$uploadPath = "uploads/{$userId}/" . date('Y-m-d');

// 定义临时凭证的权限（只能上传到用户自己的目录）
$policy = json_encode([
    'Version' => '2012-10-17',
    'Statement' => [
        [
            'Effect' => 'Allow',
            'Action' => ['s3:PutObject'],
            'Resource' => ["arn:aws:s3:::wpcollege/{$uploadPath}/*"]
        ]
    ]
]);

try {
    // 获取临时凭证（有效期 30 分钟）
    $credentials = $stsClient->assumeRole(
        1800,
        $policy,
        "upload-session-{$userId}-" . time()
    );

    // 返回给前端
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'credentials' => [
            'endpoint' => 'https://oss.kongfupack.com',
            'bucket' => 'wpcollege',
            'region' => 'us-east-1',
            'accessKeyId' => $credentials->getAccessKeyId(),
            'secretAccessKey' => $credentials->getSecretKey(),
            'sessionToken' => $credentials->getSecurityToken(),
            'uploadPath' => $uploadPath,
            'expiresIn' => 1800
        ]
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate credentials: ' . $e->getMessage()
    ]);
}
```

### 前端：使用临时凭证上传

```javascript
// 1. 获取临时凭证
async function getUploadCredentials() {
    const response = await fetch('/api/get-upload-credentials.php');
    const data = await response.json();
    return data.credentials;
}

// 2. 使用临时凭证上传文件
async function uploadFile(file) {
    const credentials = await getUploadCredentials();
    
    // 使用 AWS SDK 或 MinIO SDK
    const AWS = require('aws-sdk');
    
    const s3 = new AWS.S3({
        endpoint: credentials.endpoint,
        accessKeyId: credentials.accessKeyId,
        secretAccessKey: credentials.secretAccessKey,
        sessionToken: credentials.sessionToken,
        region: credentials.region,
        s3ForcePathStyle: true
    });
    
    const fileName = `${credentials.uploadPath}/${Date.now()}-${file.name}`;
    
    const params = {
        Bucket: credentials.bucket,
        Key: fileName,
        Body: file,
        ContentType: file.type
    };
    
    try {
        const result = await s3.upload(params).promise();
        console.log('Upload success:', result.Location);
        return result;
    } catch (error) {
        console.error('Upload failed:', error);
        throw error;
    }
}

// 3. 使用示例
document.getElementById('fileInput').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (file) {
        try {
            await uploadFile(file);
            alert('Upload successful!');
        } catch (error) {
            alert('Upload failed: ' + error.message);
        }
    }
});
```

## 常见问题排查

### 问题 1：403 Access Denied

```bash
# 检查策略是否存在
mc admin policy list myminio | grep temp-upload-policy

# 检查策略内容
mc admin policy info myminio temp-upload-policy

# 检查用户权限
mc admin user info myminio hoZyEhnhbV8ek9cHveAi
```

### 问题 2：Role ARN 不正确

尝试不同的格式，或者查看 MinIO 日志：

```bash
# 查看 MinIO 日志
docker logs minio-container

# 或者
journalctl -u minio
```

### 问题 3：MinIO 版本不支持 STS

```bash
# 检查 MinIO 版本
mc admin info myminio

# 升级 MinIO（如果需要）
# STS 功能在 RELEASE.2021-01-01 及以后版本可用
```

## 验证配置成功

如果配置成功，你应该看到：

```bash
vendor/bin/phpunit tests/StsClientIntegrationTest.php

# 输出：
# OK (4 tests, X assertions)
# 而不是 "Skipped: 3"
```

## 如果仍然无法配置 STS

不用担心，你可以使用替代方案：

1. **预签名 URL**（推荐）- 简单且不需要 STS 配置
2. **后端代理上传** - 最常用的方案
3. 详见：`docs/STS_SETUP.md` 中的"替代方案"部分

## 参考资料

- [MinIO STS 官方文档](https://min.io/docs/minio/linux/developers/security-token-service.html)
- [MinIO IAM 配置](https://min.io/docs/minio/linux/administration/identity-access-management.html)
- [AWS SDK for JavaScript](https://docs.aws.amazon.com/AWSJavaScriptSDK/latest/)
