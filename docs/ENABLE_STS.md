# 如何在你的 MinIO 上启用 STS

## 你的 MinIO 信息

- 服务器：oss.kongfupack.com
- 版本：2025-04-08T15:41:24Z ✓（支持 STS）
- 状态：正常运行
- 错误：`Unsupported action AssumeRole`

## 问题原因

你的 MinIO 版本支持 STS，但 STS 功能**默认不启用**。需要配置身份提供者才能使用。

## 检查当前配置

```bash
# 检查 OIDC 配置
mc admin config get myminio identity_openid

# 检查所有身份配置
mc admin config get myminio identity_ldap
```

如果返回空或错误，说明没有配置身份提供者。

## 启用 STS 的两种方式

### 方式 1：使用内置策略（简单，推荐）

MinIO 支持不使用 OIDC 的简化 STS 模式。

#### 步骤 1：创建服务账号

```bash
# 创建一个专门用于 STS 的服务账号
mc admin user add myminio sts-service-user StrongPassword123!

# 创建策略
cat > /tmp/sts-policy.json <<'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "sts:AssumeRole"
      ],
      "Resource": [
        "arn:aws:iam::*:role/*"
      ]
    },
    {
      "Effect": "Allow",
      "Action": [
        "s3:*"
      ],
      "Resource": [
        "arn:aws:s3:::wpcollege/*"
      ]
    }
  ]
}
EOF

# 应用策略
mc admin policy create myminio sts-policy /tmp/sts-policy.json
mc admin policy attach myminio sts-policy --user sts-service-user
```

#### 步骤 2：使用服务账号测试

```php
// 使用服务账号的凭证
$stsClient = new StsClient(
    'https://oss.kongfupack.com',
    'sts-service-user',
    'StrongPassword123!',
    'arn:aws:iam::minio:role/sts-policy',
    'us-east-1'
);
```

**注意：** 这种方式可能仍然不工作，因为 MinIO 的 STS 主要设计用于 OIDC。

### 方式 2：配置 OIDC（标准方式，但复杂）

这是 MinIO STS 的标准配置方式，需要外部身份提供者。

#### 选项 A：使用 Keycloak（开源）

1. **安装 Keycloak**

```bash
# Docker 方式
docker run -d \
  --name keycloak \
  -p 8080:8080 \
  -e KEYCLOAK_ADMIN=admin \
  -e KEYCLOAK_ADMIN_PASSWORD=admin \
  quay.io/keycloak/keycloak:latest start-dev
```

2. **配置 Keycloak**
   - 访问 http://localhost:8080
   - 创建 Realm: `minio`
   - 创建 Client: `minio-client`
   - 配置 Client 为 `confidential`
   - 获取 Client Secret

3. **配置 MinIO**

```bash
mc admin config set myminio identity_openid \
  config_url="http://localhost:8080/realms/minio/.well-known/openid-configuration" \
  client_id="minio-client" \
  client_secret="your-client-secret" \
  claim_name="policy" \
  scopes="openid,profile,email"

# 重启 MinIO
mc admin service restart myminio
```

#### 选项 B：使用 Auth0（云服务，简单）

1. 注册 Auth0 账号：https://auth0.com
2. 创建应用
3. 获取配置信息
4. 配置 MinIO：

```bash
mc admin config set myminio identity_openid \
  config_url="https://YOUR-DOMAIN.auth0.com/.well-known/openid-configuration" \
  client_id="YOUR-CLIENT-ID" \
  client_secret="YOUR-CLIENT-SECRET" \
  claim_name="policy"

mc admin service restart myminio
```

## 实际建议：不要启用 STS

### 为什么不推荐？

1. **配置复杂** - 需要额外的 OIDC 服务器
2. **维护成本高** - 需要管理身份提供者
3. **大多数场景不需要** - 预签名 URL 和后端代理已经足够
4. **调试困难** - 涉及多个系统的集成

### 什么时候才需要 STS？

只有在以下情况才真正需要 STS：
- ✓ 需要与企业 SSO（单点登录）集成
- ✓ 需要为不同用户动态分配不同权限
- ✓ 有专门的运维团队维护
- ✓ 多租户 SaaS 应用

### 对于你的项目

根据你的使用场景，**强烈建议使用替代方案**：

#### 推荐方案 1：后端代理上传

```php
// 后端完全控制
$client = new ObjectClient($endpoint, $key, $secret, $bucket);
$result = $client->upLoadObject($_FILES['file']['tmp_name'], $objectName);
```

**优点：**
- 简单可靠
- 完全控制权限
- 可以验证和处理文件
- 不需要任何额外配置

#### 推荐方案 2：预签名 URL

```php
// 生成临时下载 URL
$url = $client->getUrl($objectName, '+1 hour');

// 返回给前端
return json_encode(['url' => $url]);
```

**优点：**
- 不需要配置 STS
- 支持设置过期时间
- 适合文件下载和分享

## 快速决策树

```
需要前端直传文件？
├─ 是 → 只需要下载？
│  ├─ 是 → 使用预签名 URL ✓
│  └─ 否 → 需要上传？
│     ├─ 文件 < 100MB → 使用后端代理 ✓
│     └─ 文件 > 100MB → 考虑分片上传（不需要 STS）
└─ 否 → 使用后端代理 ✓

需要与企业 SSO 集成？
├─ 是 → 配置 OIDC + STS
└─ 否 → 不需要 STS ✓
```

## 测试 STS 是否启用

如果你配置了 OIDC，可以测试：

```bash
# 测试 STS 端点
curl -X POST https://oss.kongfupack.com \
  -d "Action=AssumeRoleWithWebIdentity" \
  -d "Version=2011-06-15" \
  -d "WebIdentityToken=YOUR-OIDC-TOKEN" \
  -d "DurationSeconds=3600"

# 如果返回凭证，说明 STS 已启用
# 如果返回 "Unsupported action"，说明仍未启用
```

## 总结

**你的选择：**

1. **不启用 STS**（推荐）
   - 使用 `examples/backend-proxy-upload.php`
   - 使用 `examples/presigned-url-upload.php`
   - 简单、可靠、满足大多数需求

2. **启用 STS**（不推荐，除非必需）
   - 配置 Keycloak 或 Auth0
   - 配置 MinIO OIDC
   - 复杂、需要维护

**建议：** 先使用后端代理方案开发项目，只有在确实需要 STS 时再考虑配置。

## 参考资料

- [MinIO STS 官方文档](https://min.io/docs/minio/linux/developers/security-token-service.html)
- [MinIO OIDC 配置](https://min.io/docs/minio/linux/operations/external-iam/configure-openid-external-identity-management.html)
- [Keycloak 文档](https://www.keycloak.org/documentation)
