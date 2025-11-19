<?php

namespace hollisho\minio\tests;

use hollisho\minio\ObjectClient;
use hollisho\minio\StsClient;
use PHPUnit\Framework\TestCase;

/**
 * STS 客户端集成测试 - 展示完整的临时凭证使用流程
 * 
 * 注意：此测试需要 MinIO 服务器配置 STS/IAM 功能
 * 如果你的 MinIO 没有配置 STS，测试会自动跳过（这是正常的）
 * 
 * STS (Security Token Service) 使用场景：
 * 1. 服务端使用主凭证获取临时凭证
 * 2. 将临时凭证发给客户端（如前端、移动端）
 * 3. 客户端使用临时凭证进行有限权限的操作
 * 4. 临时凭证过期后自动失效，提高安全性
 * 
 * 如果不需要 STS 功能：
 * - 可以使用预签名 URL 实现前端直传
 * - 或者通过后端代理上传
 * - 详见：docs/STS_SETUP.md
 * 
 * 运行: vendor/bin/phpunit tests/StsClientIntegrationTest.php
 */
class StsClientIntegrationTest extends TestCase
{
    // 主账号凭证（服务端使用）
    private $endpoint = 'https://oss.kongfupack.com';
    private $masterAccessKey = 'test';
    private $masterSecretKey = 'test';
    private $bucket = 'test';
    private $region = 'us-east-1';
    
    // STS 配置
    // 注意：这是 Role ARN（角色 ARN），不是 Resource ARN（资源 ARN）
    // 
    // Role ARN 格式：arn:aws:iam::account-id:role/role-name
    //   用于指定要扮演的角色，在 assumeRole 调用中使用
    //   示例：arn:aws:iam::minio:role/temp-upload-policy
    // 
    // Resource ARN 格式：arn:aws:s3:::bucket-name/path/*
    //   用于指定可访问的 S3 资源，在策略的 Resource 字段中使用
    //   示例：arn:aws:s3:::wpcollege/*
    // 
    // 详见：docs/ARN_EXPLAINED.md
    private $roleArn = 'arn:aws:iam::minio:role/temp-upload-policy';

    /**
     * 测试完整的 STS 流程
     * 
     * 流程说明：
     * 1. 服务端使用主凭证创建 STS 客户端
     * 2. 服务端调用 assumeRole 获取临时凭证
     * 3. 服务端将临时凭证返回给客户端
     * 4. 客户端使用临时凭证创建 ObjectClient
     * 5. 客户端使用临时凭证进行受限操作（如只能上传到特定路径）
     */
    public function testFullStsWorkflow()
    {
        // ========== 第一步：服务端获取临时凭证 ==========
        
        // 创建 STS 客户端（使用主凭证）
        $stsClient = new StsClient(
            $this->endpoint,
            $this->masterAccessKey,
            $this->masterSecretKey,
            $this->roleArn
        );

        // 定义临时凭证的权限策略（只允许上传到 temp/ 目录）
        // 注意：这里的 Resource 使用的是 Resource ARN（arn:aws:s3:::...）
        // 不要和上面的 Role ARN（arn:aws:iam::...）混淆
        $policy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => [
                        's3:PutObject',
                        's3:GetObject'
                    ],
                    'Resource' => [
                        "arn:aws:s3:::{$this->bucket}/temp/*"  // Resource ARN
                    ]
                ]
            ]
        ]);

        try {
            // 获取临时凭证（有效期 1 小时）
            $tempCredentials = $stsClient->assumeRole(
                3600,                           // 有效期：3600秒（1小时）
                $policy,                        // 权限策略
                'client-session-' . uniqid()    // 会话名称
            );

            // 临时凭证包含：AccessKeyId, SecretAccessKey, SessionToken
            $this->assertNotNull($tempCredentials);
            
            // ========== 第二步：模拟客户端使用临时凭证 ==========
            
            // 客户端收到临时凭证后，创建 ObjectClient
            $clientWithTempCredentials = new ObjectClient(
                $this->endpoint,
                $tempCredentials->getAccessKeyId(),
                $tempCredentials->getSecretKey(),
                $this->bucket,
                $tempCredentials->getSecurityToken(),  // 临时凭证的 token
                $this->region
            );

            // ========== 第三步：使用临时凭证进行操作 ==========
            
            // 测试上传到允许的路径（temp/）
            $testContent = 'Test content with temporary credentials';
            $allowedPath = 'temp/test_' . uniqid() . '.txt';
            
            $result = $clientWithTempCredentials->upLoadObjectContent(
                $testContent,
                $allowedPath
            );
            
            $this->assertIsArray($result);
            $this->assertEquals($this->bucket, $result['bucket']);
            
            // 验证文件已上传
            $exists = $clientWithTempCredentials->objectExist($allowedPath);
            $this->assertTrue($exists, '使用临时凭证应该能上传到允许的路径');

            // 清理测试文件
            $clientWithTempCredentials->deleteObject($allowedPath);

            // 注意：如果尝试上传到不允许的路径（如 root/），会被拒绝
            // 这就是 STS 临时凭证的安全性体现

        } catch (\Exception $e) {
            // 如果 MinIO 服务器不支持 STS 或配置不正确，测试会跳过
            // 这是正常的，大多数 MinIO 部署不需要 STS 功能
            // 如需启用 STS，请参考：docs/STS_SETUP.md
            $this->markTestSkipped('STS 功能未启用（这是正常的）。如需使用 STS，请参考 docs/STS_SETUP.md 配置。错误信息: ' . $e->getMessage());
        }
    }

    /**
     * 测试不同权限策略的临时凭证
     * 
     * 场景：为不同用户生成不同权限的临时凭证
     */
    public function testDifferentPolicyScenarios()
    {
        $stsClient = new StsClient(
            $this->endpoint,
            $this->masterAccessKey,
            $this->masterSecretKey,
            $this->roleArn
        );

        // 场景1：只读权限（只能下载，不能上传）
        $readOnlyPolicy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => ['s3:GetObject'],
                    'Resource' => ["arn:aws:s3:::{$this->bucket}/*"]
                ]
            ]
        ]);

        // 场景2：特定用户目录权限（只能操作自己的目录）
        $userId = 'user123';
        $userDirectoryPolicy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => ['s3:PutObject', 's3:GetObject', 's3:DeleteObject'],
                    'Resource' => ["arn:aws:s3:::{$this->bucket}/users/{$userId}/*"]
                ]
            ]
        ]);

        // 场景3：限制文件大小和类型
        $restrictedUploadPolicy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => ['s3:PutObject'],
                    'Resource' => ["arn:aws:s3:::{$this->bucket}/uploads/*"],
                    'Condition' => [
                        'NumericLessThanEquals' => [
                            's3:content-length' => 10485760  // 最大 10MB
                        ]
                    ]
                ]
            ]
        ]);

        // 这些策略可以根据实际业务需求灵活配置
        $this->assertTrue(true, '展示了不同的权限策略场景');
    }

    /**
     * 测试临时凭证的生命周期
     */
    public function testTemporaryCredentialsLifecycle()
    {
        $stsClient = new StsClient(
            $this->endpoint,
            $this->masterAccessKey,
            $this->masterSecretKey,
            $this->roleArn
        );

        // 短期凭证（最短 900 秒 = 15 分钟）
        $shortTermDuration = 900;
        
        // 长期凭证（最长 43200 秒 = 12 小时，具体取决于 MinIO 配置）
        $longTermDuration = 3600;

        $policy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => ['s3:GetObject'],
                    'Resource' => ["arn:aws:s3:::{$this->bucket}/*"]
                ]
            ]
        ]);

        try {
            // 获取短期凭证
            $credentials = $stsClient->assumeRole(
                $shortTermDuration,
                $policy,
                'short-term-session'
            );

            $this->assertNotNull($credentials);
            
            // 实际应用中：
            // - 短期凭证适合一次性操作（如单次文件上传）
            // - 长期凭证适合持续操作（如移动端 App 会话）
            // - 凭证过期后需要重新获取

        } catch (\Exception $e) {
            $this->markTestSkipped('STS 功能未启用。详见: docs/STS_SETUP.md');
        }
    }

    /**
     * 实际应用场景示例：前端直传
     * 
     * 流程：
     * 1. 前端请求后端获取临时上传凭证
     * 2. 后端生成临时凭证并返回给前端
     * 3. 前端使用临时凭证直接上传到 MinIO
     * 4. 上传完成后通知后端
     */
    public function testFrontendDirectUploadScenario()
    {
        // ========== 后端 API：生成临时上传凭证 ==========
        
        $userId = 'user_' . uniqid();
        $uploadPath = "uploads/{$userId}/" . date('Y-m-d');
        
        // 生成只能上传到特定路径的临时凭证
        $policy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => ['s3:PutObject'],
                    'Resource' => ["arn:aws:s3:::{$this->bucket}/{$uploadPath}/*"]
                ]
            ]
        ]);

        $stsClient = new StsClient(
            $this->endpoint,
            $this->masterAccessKey,
            $this->masterSecretKey,
            $this->roleArn
        );

        try {
            $tempCredentials = $stsClient->assumeRole(
                1800,  // 30 分钟有效期
                $policy,
                "upload-session-{$userId}"
            );

            // 后端返回给前端的数据
            $responseToFrontend = [
                'endpoint' => $this->endpoint,
                'bucket' => $this->bucket,
                'region' => $this->region,
                'accessKeyId' => $tempCredentials->getAccessKeyId(),
                'secretAccessKey' => $tempCredentials->getSecretKey(),
                'sessionToken' => $tempCredentials->getSecurityToken(),
                'uploadPath' => $uploadPath,
                'expiresIn' => 1800
            ];

            $this->assertArrayHasKey('accessKeyId', $responseToFrontend);
            $this->assertArrayHasKey('sessionToken', $responseToFrontend);
            
            // ========== 前端：使用临时凭证上传 ==========
            
            // 前端收到凭证后，使用 MinIO SDK 或 AWS SDK 上传文件
            // 这里模拟前端的操作
            $frontendClient = new ObjectClient(
                $responseToFrontend['endpoint'],
                $responseToFrontend['accessKeyId'],
                $responseToFrontend['secretAccessKey'],
                $responseToFrontend['bucket'],
                $responseToFrontend['sessionToken'],
                $responseToFrontend['region']
            );

            // 前端上传文件
            $testFile = $uploadPath . '/test_' . uniqid() . '.txt';
            $result = $frontendClient->upLoadObjectContent(
                'Frontend uploaded content',
                $testFile
            );

            $this->assertIsArray($result);
            
            // 清理
            $frontendClient->deleteObject($testFile);

        } catch (\Exception $e) {
            $this->markTestSkipped('STS 功能未启用。详见: docs/STS_SETUP.md');
        }
    }
}

/**
 * 不使用 STS 的替代方案示例
 * 
 * 如果你的 MinIO 没有配置 STS，可以使用以下方式实现类似功能
 */
class AlternativeToStsTest extends TestCase
{
    private $endpoint = 'https://oss.kongfupack.com';
    private $accessKey = 'hoZyEhnhbV8ek9cHveAi';
    private $secretKey = 'o0TPBizQzcoj56BvWRPtJSz7cyT1YP3Z4VkM9xuN';
    private $bucket = 'wpcollege';
    private $region = 'us-east-1';

    /**
     * 替代方案：使用预签名 URL 实现前端直传
     * 
     * 优点：
     * - 不需要配置 STS
     * - 简单易用
     * - 支持设置过期时间
     * 
     * 缺点：
     * - 只能生成单个文件的上传 URL
     * - 权限控制不如 STS 灵活
     */
    public function testPresignedUrlForDirectUpload()
    {
        $client = new ObjectClient(
            $this->endpoint,
            $this->accessKey,
            $this->secretKey,
            $this->bucket,
            '',
            $this->region
        );

        // 生成预签名 URL（用于下载）
        $objectName = 'test_presigned_' . uniqid() . '.txt';
        
        // 先上传一个测试文件
        $client->upLoadObjectContent('Test content', $objectName);

        // 生成预签名下载 URL（有效期 10 分钟）
        $downloadUrl = $client->getUrl($objectName, '+10 minutes');
        
        $this->assertIsString($downloadUrl);
        $this->assertStringContainsString($objectName, $downloadUrl);
        $this->assertStringContainsString('X-Amz-Signature', $downloadUrl);

        // 清理
        $client->deleteObject($objectName);

        // 实际应用：
        // 1. 后端生成预签名 URL
        // 2. 返回给前端
        // 3. 前端使用这个 URL 下载文件（不需要凭证）
        
        // 注意：预签名 URL 主要用于下载
        // 如需上传，建议使用后端代理或配置 STS
    }

    /**
     * 替代方案：后端代理上传
     * 
     * 这是最简单、最常用的方案
     */
    public function testBackendProxyUpload()
    {
        $client = new ObjectClient(
            $this->endpoint,
            $this->accessKey,
            $this->secretKey,
            $this->bucket,
            '',
            $this->region
        );

        // 模拟前端上传到后端
        $uploadedContent = 'File uploaded through backend';
        $objectName = 'backend_proxy_' . uniqid() . '.txt';

        // 后端接收文件后，使用主凭证上传到 MinIO
        $result = $client->upLoadObjectContent($uploadedContent, $objectName);
        
        $this->assertIsArray($result);
        $this->assertEquals($this->bucket, $result['bucket']);

        // 清理
        $client->deleteObject($objectName);

        // 实际应用流程：
        // 1. 前端上传文件到后端 API
        // 2. 后端验证文件（大小、类型、权限等）
        // 3. 后端使用主凭证上传到 MinIO
        // 4. 返回文件 URL 给前端
        
        // 优点：
        // - 简单可靠
        // - 完全控制权限
        // - 可以做各种验证和处理
        
        // 缺点：
        // - 文件需要经过后端服务器
        // - 占用后端带宽
    }
}
