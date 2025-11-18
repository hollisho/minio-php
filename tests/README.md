# 单元测试说明

## 安装依赖

```bash
composer install
```

## 配置测试环境

### 方式一：使用 .env 文件（推荐）
复制 `.env.example` 为 `.env` 并填入你的 MinIO 配置：
```bash
cp .env.example .env
```

然后编辑 `.env` 文件，填入真实的配置信息。

### 方式二：设置环境变量
在运行测试前设置环境变量：
```bash
# Windows CMD
set MINIO_ENDPOINT=https://oss.kongfupack.com
set MINIO_ACCESS_KEY=hollis
set MINIO_SECRET_KEY=123123
set MINIO_BUCKET=wpcollege

# Windows PowerShell
$env:MINIO_ENDPOINT="https://oss.kongfupack.com"
$env:MINIO_ACCESS_KEY="hollis"
$env:MINIO_SECRET_KEY="123123"
$env:MINIO_BUCKET="wpcollege"

# Linux/Mac
export MINIO_ENDPOINT=https://oss.kongfupack.com
export MINIO_ACCESS_KEY=hollis
export MINIO_SECRET_KEY=123123
export MINIO_BUCKET=wpcollege
```

## 运行测试

运行所有单元测试（不需要 MinIO 服务器）：
```bash
vendor/bin/phpunit --exclude-group integration
```

运行所有测试（包括集成测试）：
```bash
vendor/bin/phpunit
```

运行特定测试文件：
```bash
# 单元测试
vendor/bin/phpunit tests/StringHelperTest.php
vendor/bin/phpunit tests/ObjectClientTest.php
vendor/bin/phpunit tests/StsClientTest.php

# 集成测试（需要真实 MinIO 服务器）
vendor/bin/phpunit tests/IntegrationTest.php
```

运行测试并生成覆盖率报告：
```bash
vendor/bin/phpunit --coverage-html coverage
```

## 测试说明

### 单元测试

#### StringHelperTest
测试 `StringHelper` 类的 `toGuidString` 方法：
- 字符串输入测试
- 数组输入测试
- 对象输入测试
- 一致性测试
- 不同输入产生不同结果测试

#### ObjectClientTest
测试 `ObjectClient` 类的基本功能：
- 构造函数测试
- 带 token 的构造函数测试
- 单例模式测试
- 不同参数产生不同实例测试

#### StsClientTest
测试 `StsClient` 类的基本功能：
- 构造函数测试
- 单例模式测试
- 不同参数产生不同实例测试
- 空 roleArn 测试

### 集成测试

#### IntegrationTest
测试与真实 MinIO 服务器的交互：
- Bucket 存在性检查
- 文件上传和删除
- 内容上传
- 对象复制
- 批量操作（批量上传、批量删除）
- 获取对象列表
- 获取对象 URL
- 获取对象元数据

**注意：** 集成测试需要配置真实的 MinIO 服务器信息，如果未配置会自动跳过。

## 测试最佳实践

1. **单元测试**：快速验证代码逻辑，不依赖外部服务
2. **集成测试**：验证与 MinIO 服务器的实际交互
3. **持续集成**：在 CI/CD 中只运行单元测试，集成测试在特定环境运行
4. **测试隔离**：每个测试用例独立，清理测试数据

## 安全提示

- 不要将 `.env` 文件提交到版本控制系统
- 使用测试专用的 MinIO bucket
- 定期清理测试产生的临时文件
