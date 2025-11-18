<?php

namespace hollisho\minio\tests;

use hollisho\minio\ObjectClient;
use hollisho\minio\StsClient;
use PHPUnit\Framework\TestCase;

/**
 * 集成测试 - 需要真实的 MinIO 服务器
 * 
 * 使用前请配置 .env 文件或设置环境变量
 * 运行: vendor/bin/phpunit tests/IntegrationTest.php
 */
class IntegrationTest extends TestCase
{
    private $endpoint = 'https://oss.kongfupack.com';
    private $accessKey = 'hoZyEhnhbV8ek9cHveAi';
    private $secretKey = 'o0TPBizQzcoj56BvWRPtJSz7cyT1YP3Z4VkM9xuN';
    private $bucket = 'wpcollege';
    private $region = 'us-east-1';
    private $client;

    protected function setUp(): void
    {
        $this->client = new ObjectClient(
            $this->endpoint,
            $this->accessKey,
            $this->secretKey,
            $this->bucket,
            '',
            $this->region
        );
    }

    public function testBucketExists()
    {
        $exists = $this->client->bucketExists($this->bucket);
        $this->assertTrue($exists, "Bucket {$this->bucket} 应该存在");
    }

    public function testUploadAndDeleteObject()
    {
        // 创建临时测试文件
        $testContent = 'This is a test file for MinIO integration test';
        $tempFile = sys_get_temp_dir() . '/minio_test_' . uniqid() . '.txt';
        file_put_contents($tempFile, $testContent);

        try {
            // 测试上传
            $objectName = 'test_' . uniqid() . '.txt';
            $result = $this->client->upLoadObject($tempFile, $objectName);
            
            $this->assertIsArray($result);
            $this->assertEquals($this->bucket, $result['bucket']);
            $this->assertEquals($objectName, $result['name']);

            // 测试对象是否存在
            $exists = $this->client->objectExist($objectName);
            $this->assertTrue($exists, "上传的对象应该存在");

            // 测试获取 URL
            $url = $this->client->getUrl($objectName, '+10 minutes');
            $this->assertIsString($url);
            $this->assertStringContainsString($objectName, $url);

            // 测试获取元数据
            $metadata = $this->client->getMetaData($objectName);
            $this->assertIsArray($metadata);

            // 测试删除
            $deleted = $this->client->deleteObject($objectName);
            $this->assertTrue($deleted, "对象应该被成功删除");

            // 验证对象已被删除
            $exists = $this->client->objectExist($objectName);
            $this->assertFalse($exists, "删除后对象不应该存在");

        } finally {
            // 清理临时文件
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testUploadObjectContent()
    {
        $content = 'Test content for upload';
        $objectPath = 'test_content_' . uniqid() . '.txt';

        try {
            // 测试上传内容
            $result = $this->client->upLoadObjectContent($content, $objectPath);
            
            $this->assertIsArray($result);
            $this->assertEquals($this->bucket, $result['bucket']);

            // 验证对象存在
            $exists = $this->client->objectExist($objectPath);
            $this->assertTrue($exists);

        } finally {
            // 清理
            $this->client->deleteObject($objectPath);
        }
    }

    public function testCopyObject()
    {
        // 先创建一个源对象
        $content = 'Source object content';
        $sourceObject = 'source_' . uniqid() . '.txt';
        $targetObject = 'target_' . uniqid() . '.txt';

        try {
            // 上传源对象
            $this->client->upLoadObjectContent($content, $sourceObject);

            // 测试复制
            $result = $this->client->copyObject($sourceObject, $targetObject);
            
            $this->assertIsArray($result);
            $this->assertEquals($targetObject, $result['name']);

            // 验证目标对象存在
            $exists = $this->client->objectExist($targetObject);
            $this->assertTrue($exists);

        } finally {
            // 清理
            $this->client->deleteObject($sourceObject);
            $this->client->deleteObject($targetObject);
        }
    }

    public function testBatchOperations()
    {
        $objects = [];
        $tempFiles = [];

        try {
            // 创建多个测试文件
            for ($i = 0; $i < 3; $i++) {
                $tempFile = sys_get_temp_dir() . '/minio_batch_test_' . $i . '_' . uniqid() . '.txt';
                file_put_contents($tempFile, "Test content $i");
                $tempFiles[] = $tempFile;
            }

            // 批量上传
            $results = $this->client->batchUpload($tempFiles);
            
            $this->assertIsArray($results);
            $this->assertCount(3, $results);

            // 收集对象名称用于删除
            foreach ($results as $result) {
                $objects[] = $result['name'];
            }

            // 验证所有对象都存在
            foreach ($objects as $object) {
                $exists = $this->client->objectExist($object);
                $this->assertTrue($exists);
            }

            // 批量删除
            $deleted = $this->client->batchDeleteObject($objects);
            $this->assertTrue($deleted);

        } finally {
            // 清理临时文件
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        }
    }

    public function testGetAll()
    {
        $objectName = 'test_getall_' . uniqid() . '.txt';

        try {
            // 上传一个测试对象
            $this->client->upLoadObjectContent('test', $objectName);

            // 测试获取所有对象
            $objects = $this->client->getAll(['num' => 10]);
            
            $this->assertIsArray($objects);

            // 测试带前缀的查询
            $prefix = 'test_getall_';
            $objects = $this->client->getAll(['prefix' => $prefix]);
            
            $this->assertIsArray($objects);

        } finally {
            // 清理
            $this->client->deleteObject($objectName);
        }
    }
}
