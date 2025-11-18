# ARN (Amazon Resource Name) 详解

在 MinIO/AWS 中，ARN 用于标识不同类型的资源。有两种主要的 ARN 类型需要区分：

## 1. Resource ARN（资源 ARN）

用于在策略中指定**哪些资源**可以被访问。

### S3 资源 ARN 格式

```
arn:aws:s3:::bucket-name/path/*
```

**示例：**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:PutObject", "s3:GetObject"],
      "Resource": [
        "arn:aws:s3:::wpcollege/*",           // 允许访问整个 bucket
        "arn:aws:s3:::wpcollege/temp/*",     // 只允许访问 temp/ 目录
        "arn:aws:s3:::wpcollege/uploads/*",  // 只允许访问 uploads/ 目录
        "arn:aws:s3:::*"                     // 允许访问所有 bucket（不推荐）
      ]
    }
  ]
}
```

**Resource ARN 的组成部分：**
- `arn:aws:s3:::` - 固定前缀，表示 S3 资源
- `bucket-name` - Bucket 名称
- `/path/*` - 路径（可选），`*` 表示通配符

## 2. Role ARN（角色 ARN）

用于在 STS `assumeRole` 调用中指定**要扮演的角色**。

### Role ARN 格式

```
arn:aws:iam::account-id:role/role-name
```

**MinIO 中的 Role ARN 示例：**

```php
// 格式 1：使用 minio 作为 account-id
$roleArn = 'arn:aws:iam::minio:role/temp-upload-policy';

// 格式 2：使用数字 account-id
$roleArn = 'arn:aws:iam::123456789012:role/temp-upload-policy';

// 格式 3：MinIO 特定格式
$roleArn = 'arn:minio:iam:::role/temp-upload-policy';
```

**Role ARN 的组成部分：**
- `arn:aws:iam::` - 固定前缀，表示 IAM 资源
- `account-id` - 账号 ID（MinIO 中通常是 `minio` 或数字）
- `role/` - 固定部分，表示这是一个角色
- `role-name` - 角色名称（通常是策略名称）

## 完整示例：两种 ARN 的使用

### 在 MinIO 策略中使用 Resource ARN

```bash
# 创建策略文件
cat > temp-upload-policy.json <<'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:PutObject", "s3:GetObject"],
      "Resource": [
        "arn:aws:s3:::wpcollege/temp/*",      // ← 这是 Resource ARN
        "arn:aws:s3:::wpcollege/uploads/*"    // ← 这是 Resource ARN
      ]
    }
  ]
}
EOF

# 创建策略
mc admin policy create myminio temp-upload-policy temp-upload-policy.json
```

### 在 PHP 代码中使用 Role ARN

```php
use hollisho\minio\StsClient;

// 创建 STS 客户端，使用 Role ARN
$stsClient = new StsClient(
    'https://oss.kongfupack.com',
    'your-access-key',
    'your-secret-key',
    'arn:aws:iam::minio:role/temp-upload-policy',  // ← 这是 Role ARN
    'us-east-1'
);

// 定义临时凭证的权限策略（使用 Resource ARN）
$policy = json_encode([
    'Version' => '2012-10-17',
    'Statement' => [
        [
            'Effect' => 'Allow',
            'Action' => ['s3:PutObject'],
            'Resource' => [
                'arn:aws:s3:::wpcollege/temp/*'    // ← 这是 Resource ARN
            ]
        ]
    ]
]);

// 获取临时凭证
$credentials = $stsClient->assumeRole(3600, $policy, 'session-name');
```

## 常见配置示例

### 示例 1：允许访问整个 Bucket

```json
{
  "Resource": ["arn:aws:s3:::wpcollege/*"]
}
```

### 示例 2：允许访问特定目录

```json
{
  "Resource": [
    "arn:aws:s3:::wpcollege/temp/*",
    "arn:aws:s3:::wpcollege/uploads/*"
  ]
}
```

### 示例 3：允许访问用户自己的目录

```json
{
  "Resource": ["arn:aws:s3:::wpcollege/users/${aws:username}/*"]
}
```

### 示例 4：允许访问所有 Bucket（不推荐）

```json
{
  "Resource": ["arn:aws:s3:::*"]
}
```

## 你的配置应该是什么？

根据你的 MinIO 配置，你需要：

### 1. 在 MinIO 中创建策略（使用 Resource ARN）

```bash
cat > temp-upload-policy.json <<'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"],
      "Resource": ["arn:aws:s3:::wpcollege/*"]
    }
  ]
}
EOF

mc admin policy create myminio temp-upload-policy temp-upload-policy.json
```

### 2. 在 PHP 代码中使用 Role ARN

```php
// tests/StsClientIntegrationTest.php
private $roleArn = 'arn:aws:iam::minio:role/temp-upload-policy';
```

### 3. 在 assumeRole 中使用 Resource ARN

```php
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
```

## 快速检查清单

- [ ] MinIO 策略文件中的 `Resource` 使用：`arn:aws:s3:::bucket-name/*`
- [ ] PHP 代码中的 `$roleArn` 使用：`arn:aws:iam::minio:role/policy-name`
- [ ] `assumeRole` 的 `$policy` 参数中的 `Resource` 使用：`arn:aws:s3:::bucket-name/*`

## 总结

| ARN 类型 | 用途 | 格式 | 示例 |
|---------|------|------|------|
| **Resource ARN** | 指定可访问的 S3 资源 | `arn:aws:s3:::bucket/path/*` | `arn:aws:s3:::wpcollege/*` |
| **Role ARN** | 指定要扮演的 IAM 角色 | `arn:aws:iam::account:role/name` | `arn:aws:iam::minio:role/temp-upload-policy` |

**记住：**
- `arn:aws:s3:::*` 是 **Resource ARN**，用于策略中的 `Resource` 字段
- `arn:aws:iam::minio:role/xxx` 是 **Role ARN**，用于 `assumeRole` 调用
