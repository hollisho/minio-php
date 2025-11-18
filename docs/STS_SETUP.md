# MinIO STS (Security Token Service) 配置指南

## 什么是 STS？

STS (Security Token Service) 是一种临时凭证服务，允许你为客户端生成有限权限和有效期的临时访问凭证。

## 使用场景

1. **前端直传**：前端直接上传文件到 MinIO，不经过后端服务器
2. **移动端应用**：为移动端 App 提供临时访问权限
3. **第三方集成**：为第三方应用提供临时访问授权
4. **安全隔离**：不同用户只能访问自己的目录

## 是否需要 STS？

**不需要 STS 的场景：**
- 只有后端服务器访问 MinIO
- 所有文件操作都通过后端 API
- 简单的文件存储需求

**需要 STS 的场景：**
- 前端/移动端需要直接上传文件到 MinIO
- 需要为不同用户分配不同权限
- 需要临时访问授权

## MinIO STS 配置步骤

### 前置要求

1. 安装 MinIO Client (mc)：
```bash
# Linux/Mac
wget https://dl.min.io/client/mc/release/linux-amd64/mc
chmod +x mc
sudo mv mc /usr/local/bin/

# Windows
# 下载 https://dl.min.io/client/mc/release/windows-amd64/mc.exe
```

2. 配置 mc 连接到你的 MinIO：
```bash
mc alias set myminio https://oss.kongfupack.com your-access-key your-secret-key
```

### 1. 创建 IAM 策略

```bash
# 创建策略文件 temp-upload-policy.json
cat > temp-upload-policy.json <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": [
        "arn:aws:s3:::wpcollege/temp/*",
        "arn:aws:s3:::wpcollege/uploads/*"
      ]
    }
  ]
}
EOF

# 添加策略到 MinIO
mc admin policy create myminio temp-upload-policy temp-upload-policy.json
```

### 2. 创建服务账号（用于 STS）

```bash
# 创建一个服务账号用于 AssumeRole
mc admin user add myminio sts-service-account your-password

# 将策略分配给服务账号
mc admin policy attach myminio temp-upload-policy --user sts-service-account
```

### 3. 获取 Role ARN

MinIO 的 Role ARN 格式通常是：
```
arn:aws:iam::ACCOUNT-ID:role/ROLE-NAME
```

对于 MinIO，你可以使用：
```bash
# 查看现有策略
mc admin policy list myminio

# 查看策略详情
mc admin policy info myminio temp-upload-policy
```

**常见的 MinIO Role ARN 格式：**
```
# 格式 1：使用策略名称
arn:aws:iam::minio:role/temp-upload-policy

# 格式 2：使用账号 ID
arn:aws:iam::123456789012:role/temp-upload-policy

# 格式 3：简化格式（某些 MinIO 版本）
arn:minio:iam:::role/temp-upload-policy
```

### 4. 在代码中使用正确的 Role ARN

```php
// 方式 1：使用你在 MinIO 中创建的策略名称
$roleArn = 'arn:aws:iam::minio:role/temp-upload-policy';

// 方式 2：如果 MinIO 配置了账号 ID
$roleArn = 'arn:aws:iam::123456789012:role/temp-upload-policy';

$stsClient = new StsClient(
    'https://oss.kongfupack.com',
    'your-access-key',
    'your-secret-key',
    $roleArn  // 必须与 MinIO 中的配置一致
);
```

### 5. 验证 STS 配置

```bash
# 测试 STS 功能
mc admin info myminio

# 查看服务账号
mc admin user list myminio

# 查看策略
mc admin policy list myminio
```

### 6. 测试 AssumeRole

```php
// 测试代码
$stsClient = new StsClient(
    'https://oss.kongfupack.com',
    'your-access-key',
    'your-secret-key',
    'arn:aws:iam::minio:role/temp-upload-policy'
);

$policy = json_encode([
    'Version' => '2012-10-17',
    'Statement' => [
        [
            'Effect' => 'Allow',
            'Action' => ['s3:PutObject'],
            'Resource' => ['arn:aws:s3:::wpcollege/temp/*']
        ]
    ]
]);

try {
    $credentials = $stsClient->assumeRole(3600, $policy, 'test-session');
    echo "STS 配置成功！\n";
    echo "临时 AccessKey: " . $credentials->getAccessKeyId() . "\n";
} catch (Exception $e) {
    echo "STS 配置失败: " . $e->getMessage() . "\n";
}
```

## 替代方案：不使用 STS

如果不需要 STS 功能，可以使用以下替代方案：

### 方案 1：后端代理上传

```php
// 前端上传到后端
// 后端使用主凭证上传到 MinIO
$client = new ObjectClient($endpoint, $key, $secret, $bucket);
$result = $client->upLoadObject($_FILES['file']['tmp_name'], $filename);
```

### 方案 2：预签名 URL

```php
// 后端生成预签名 URL
$client = new ObjectClient($endpoint, $key, $secret, $bucket);
$url = $client->getUrl($objectName, '+1 hour');

// 前端使用预签名 URL 直接上传（使用 PUT 请求）
// 这种方式不需要 STS，但功能有限
```

### 方案 3：使用 MinIO 的 Presigned POST

```php
// 生成 presigned POST 表单数据
// 前端使用表单直接上传到 MinIO
// 这是最接近 STS 的替代方案，不需要配置 IAM
```

## 常见错误

### 403 Access Denied

```
Error: AccessDenied (client): Access Denied.
```

**原因：**
- MinIO 未启用 STS 功能
- IAM 角色未配置
- Role ARN 不正确或不存在
- 权限策略不正确
- 使用的凭证没有 AssumeRole 权限

**解决方案：**

1. **检查 Role ARN 是否正确**
```bash
# 查看 MinIO 中的策略
mc admin policy list myminio

# 确认策略名称，然后使用正确的 ARN 格式
# 例如：arn:aws:iam::minio:role/your-policy-name
```

2. **确认凭证有 AssumeRole 权限**
```bash
# 查看用户权限
mc admin user info myminio your-access-key

# 确保用户有 sts:AssumeRole 权限
```

3. **检查 MinIO 版本**
```bash
mc admin info myminio

# 某些旧版本的 MinIO 不支持 STS
# 建议使用 MinIO RELEASE.2021-01-01 或更新版本
```

4. **尝试不同的 Role ARN 格式**
```php
// 尝试这些格式，看哪个有效
$roleArnFormats = [
    'arn:aws:iam::minio:role/temp-upload-policy',
    'arn:aws:iam::123456789012:role/temp-upload-policy',
    'arn:minio:iam:::role/temp-upload-policy',
];
```

5. **如果仍然失败，使用替代方案**
   - 预签名 URL（推荐）
   - 后端代理上传
   - 详见下方"替代方案"部分

## 测试 STS 功能

```bash
# 运行 STS 集成测试
vendor/bin/phpunit tests/StsClientIntegrationTest.php

# 如果看到 "STS 功能不可用" 的跳过消息，说明：
# 1. MinIO 未配置 STS（这是正常的）
# 2. 可以继续使用其他功能
# 3. 如果需要 STS，按照上述步骤配置
```

## 参考资料

- [MinIO STS 官方文档](https://min.io/docs/minio/linux/developers/security-token-service.html)
- [MinIO IAM 配置](https://min.io/docs/minio/linux/administration/identity-access-management.html)
- [AWS STS 概念](https://docs.aws.amazon.com/STS/latest/APIReference/welcome.html)

## 总结

- STS 是可选功能，不是必需的
- 大多数简单应用不需要 STS
- 如果需要前端直传，可以先尝试预签名 URL
- 只有在需要复杂权限控制时才配置 STS
