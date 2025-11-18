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

// 创建 STS 客户端
$stsClient = new StsClient(
    'https://oss.kongfupack.com',
    'your-access-key',
    'your-secret-key',
    'arn:aws:iam::123456789012:role/your-role'
);

// 获取临时凭证
$credentials = $stsClient->assumeRole(
    3600,                           // 有效期（秒）
    '{"Version":"2012-10-17",...}', // 策略
    'session-name'                  // 会话名称
);
```

## Testing

查看详细测试说明：[tests/README.md](tests/README.md)

### 快速开始

```bash
# 安装依赖
composer install

# 运行单元测试
vendor/bin/phpunit --exclude-group integration

# 配置环境变量后运行集成测试
vendor/bin/phpunit tests/IntegrationTest.php
```