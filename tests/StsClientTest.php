<?php

namespace hollisho\minio\tests;

use hollisho\minio\StsClient;
use PHPUnit\Framework\TestCase;

class StsClientTest extends TestCase
{
    private $endpoint = 'http://localhost:9000';
    private $key = 'test-key';
    private $secret = 'test-secret';
    private $roleArn = 'arn:aws:iam::123456789012:role/test-role';

    public function testConstructor()
    {
        $client = new StsClient($this->endpoint, $this->key, $this->secret, $this->roleArn);
        
        $this->assertInstanceOf(StsClient::class, $client);
    }

    public function testGetInstanceReturnsSameInstance()
    {
        $client1 = StsClient::getInstance($this->endpoint, $this->key, $this->secret, $this->roleArn);
        $client2 = StsClient::getInstance($this->endpoint, $this->key, $this->secret, $this->roleArn);
        
        $this->assertSame($client1, $client2);
    }

    public function testGetInstanceReturnsDifferentInstanceForDifferentParams()
    {
        $client1 = StsClient::getInstance($this->endpoint, $this->key, $this->secret, $this->roleArn);
        $client2 = StsClient::getInstance($this->endpoint, 'different-key', $this->secret, $this->roleArn);
        
        $this->assertNotSame($client1, $client2);
    }

    public function testGetInstanceWithEmptyRoleArn()
    {
        $client = StsClient::getInstance($this->endpoint, $this->key, $this->secret, '');
        
        $this->assertInstanceOf(StsClient::class, $client);
    }
}
