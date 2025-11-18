# STS 403 Access Denied 问题排查

## 问题现象

### 错误 1：Access Denied
```
Client error: POST https://oss.kongfupack.com resulted in a 403 Forbidden response
AccessDenied (client): Access Denied.
```

### 错误 2：Unsupported action AssumeRole（你的情况）
```xml
<ErrorResponse xmlns="https://sts.amazonaws.com/doc/2011-06-15/">
  <Error>
    <Type></Type>
    <Code>InvalidParameterValue</Code>
    <Message>Unsupported action AssumeRole</Message>
  </Error>
  <RequestId>187906BEF613E12E</RequestId>
</ErrorResponse>
```

**这个错误明确说明：你的 MinIO 服务器不支持 STS AssumeRole 功能。**

## 根本原因

MinIO 的 STS 功能需要特定的配置才能启用。大多数 MinIO 部署默认**不启用** STS 功能。

当你看到 `Unsupported action AssumeRole` 错误时，说明：
1. ✓ MinIO 服务器收到了请求
2. ✓ 认证通过了
3. ✗ 但 MinIO 没有启用 STS 模块

## 快速检查：你的 MinIO 是否支持 STS？

### 方法 1：检查 MinIO 版本

```bash
mc admin info myminio

# 查看输出中的版本信息
# STS 功能需要 MinIO RELEASE.2021-01-01 或更新版本
```

### 方法 2：尝试直接调用 STS API

```bash
curl -X POST https://oss.kongfupack.com \
  -d "Action=AssumeRole" \
  -d "Version=2011-06-15" \
  -d "DurationSeconds=3600" \
  -d "Policy={}" \
  -d "RoleArn=arn:aws:iam::minio:role/test" \
  -d "RoleSessionName=test"

# 可能的返回结果：
# 1. "Unsupported action AssumeRole" → STS 未启用（你的情况）
# 2. "Access Denied" → STS 未启用或权限不足
# 3. "InvalidRole" → STS 已启用，但角色配置有问题
```

### 方法 3：检查 MinIO 环境变量

STS 功能需要 MinIO 配置身份提供者（Identity Provider）。检查 MinIO 是否配置了以下环境变量：

```bash
# 查看 MinIO 配置
mc admin config get myminio identity_openid

# 或者检查 MinIO 启动参数
ps aux | grep minio
```

## 常见原因和解决方案

### 原因 1：MinIO 未启用 STS（最常见）

**症状：** 所有 STS 请求都返回 403 Access Denied

**解决方案：** MinIO 的 STS 功能需要配置 OpenID Connect (OIDC) 身份提供者才能启用。

**配置步骤：**

```bash
# 1. 配置 OIDC（示例使用 Keycloak）
mc admin config set myminio identity_openid \
  config_url="https://your-keycloak.com/auth/realms/minio/.well-known/openid-configuration" \
  client_id="minio" \
  claim_name="policy"

# 2. 重启 MinIO
mc admin service restart myminio
```

**问题：** 这需要额外的 OIDC 服务器，配置复杂。

### 原因 2：使用的凭证没有 STS 权限

**症状：** 特定凭证返回 403，其他操作正常

**解决方案：** 确保使用的 Access Key 有 `sts:AssumeRole` 权限

```bash
# 检查用户权限
mc admin user info myminio your-access-key

# 创建具有 STS 权限的策略
cat > sts-policy.json <<'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["sts:AssumeRole"],
      "Resource": ["arn:aws:iam::*:role/*"]
    }
  ]
}
EOF

mc admin policy create myminio sts-policy sts-policy.json
mc admin policy attach myminio sts-policy --user your-access-key
```

### 原因 3：MinIO 版本太旧

**症状：** MinIO 版本低于 RELEASE.2021-01-01

**解决方案：** 升级 MinIO 到最新版本

```bash
# 备份数据
mc mirror myminio/bucket /backup/bucket

# 升级 MinIO（Docker 示例）
docker pull minio/minio:latest
docker stop minio
docker rm minio
# 使用新镜像重新启动
```

## 确认：你的 MinIO 不支持 STS

如果你看到 `Unsupported action AssumeRole` 错误，这意味着：

**你的 MinIO 服务器明确表示不支持 STS 功能。**

这是**完全正常的**，因为：
1. MinIO 的 STS 功能是可选的
2. 需要额外配置 OIDC 身份提供者
3. 大多数部署不需要 STS

## 实际情况：大多数 MinIO 不需要 STS

### 为什么 STS 配置这么复杂？

STS 是企业级功能，主要用于：
- 与 OIDC/LDAP/AD 集成
- 多租户环境
- 复杂的权限管理

对于大多数应用场景，**不需要 STS**。

### 启用 STS 需要什么？

要启用 STS，你需要：
1. **OIDC 服务器**（如 Keycloak、Auth0、Okta）
2. **配置 MinIO 连接到 OIDC**
3. **配置角色映射**
4. **重启 MinIO 服务**

这是一个复杂的企业级配置，不建议个人项目使用。

## 推荐的替代方案

### 方案 1：预签名 URL（最推荐）

**优点：**
- 不需要配置 STS
- 简单易用
- MinIO 原生支持

**实现：**

```php
use hollisho\minio\ObjectClient;

$client = new ObjectClient($endpoint, $key, $secret, $bucket);

// 生成上传 URL（使用 PUT 方法）
$objectName = 'uploads/' . uniqid() . '.jpg';
$uploadUrl = $client->getUrl($objectName, '+1 hour');

// 返回给前端
return json_encode([
    'uploadUrl' => $uploadUrl,
    'method' => 'PUT',
    'objectName' => $objectName
]);
```

**前端使用：**

```javascript
// 前端直接上传
async function uploadFile(file, uploadUrl) {
    const response = await fetch(uploadUrl, {
        method: 'PUT',
        body: file,
        headers: {
            'Content-Type': file.type
        }
    });
    return response.ok;
}
```

### 方案 2：后端代理上传

**优点：**
- 完全控制权限
- 可以验证文件
- 不需要任何特殊配置

**实现：**

```php
// 后端 API
use hollisho\minio\ObjectClient;

$client = new ObjectClient($endpoint, $key, $secret, $bucket);

// 接收前端上传的文件
$file = $_FILES['file'];

// 验证文件
if ($file['size'] > 10 * 1024 * 1024) {
    die('File too large');
}

// 上传到 MinIO
$objectName = 'uploads/' . uniqid() . '-' . $file['name'];
$result = $client->upLoadObject($file['tmp_name'], $objectName);

// 返回文件 URL
$url = $client->getUrl($objectName, '+1 year');
return json_encode(['url' => $url]);
```

### 方案 3：MinIO Presigned POST（推荐用于前端直传）

这是最接近 STS 的方案，但不需要配置 STS。

**后端生成 POST 表单数据：**

```php
use Aws\S3\PostObjectV4;

$client = new ObjectClient($endpoint, $key, $secret, $bucket);

// 获取底层 S3 客户端
$s3Client = $client->getClient(); // 需要添加 getter 方法

// 生成 POST 表单
$postObject = new PostObjectV4(
    $s3Client,
    $bucket,
    ['key' => 'uploads/${filename}'],
    [
        ['bucket' => $bucket],
        ['starts-with', '$key', 'uploads/'],
        ['content-length-range', 0, 10485760] // 最大 10MB
    ],
    '+1 hour'
);

// 返回给前端
return json_encode([
    'url' => $postObject->getFormAttributes()['action'],
    'fields' => $postObject->getFormInputs()
]);
```

**前端使用：**

```javascript
async function uploadWithPresignedPost(file, postData) {
    const formData = new FormData();
    
    // 添加所有表单字段
    Object.keys(postData.fields).forEach(key => {
        formData.append(key, postData.fields[key]);
    });
    
    // 添加文件（必须最后添加）
    formData.append('file', file);
    
    const response = await fetch(postData.url, {
        method: 'POST',
        body: formData
    });
    
    return response.ok;
}
```

## 你的 MinIO 版本

根据你的信息：
- 版本：2025-04-08T15:41:24Z ✓（支持 STS）
- 错误：`Unsupported action AssumeRole`

**结论：** 你的 MinIO 版本支持 STS，但需要配置 OIDC 才能启用。

详见：`docs/ENABLE_STS.md`

## 总结

### 如果你看到 "Unsupported action AssumeRole"：

1. **不要纠结于 STS** - 配置复杂，大多数情况下不需要
2. **使用预签名 URL** - 简单且满足大多数需求
3. **使用后端代理** - 最可靠的方案
4. **只有在以下情况才配置 STS：**
   - 需要与企业 SSO/OIDC/LDAP 集成
   - 需要复杂的多租户权限管理
   - 有专门的运维团队支持
   - 愿意配置和维护 OIDC 服务器

### 修改测试代码

由于你的 MinIO 不支持 STS，建议注释掉 STS 测试，专注于实际可用的功能：

```php
// tests/IntegrationTest.php 中已经有完整的测试
// STS 测试会自动跳过，这是正常的

// 运行实际可用的测试
vendor/bin/phpunit tests/IntegrationTest.php
vendor/bin/phpunit tests/StsClientIntegrationTest.php::testPresignedUrlForDirectUpload
```

## 需要帮助？

如果你确实需要 STS 功能，建议：
1. 联系 MinIO 服务器管理员
2. 查看 MinIO 官方文档配置 OIDC
3. 或者考虑使用 MinIO 的商业支持

但对于大多数应用，预签名 URL 或后端代理已经足够。
