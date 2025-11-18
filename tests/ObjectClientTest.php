<?php

namespace hollisho\minio\tests;

use hollisho\minio\ObjectClient;
use PHPUnit\Framework\TestCase;

class ObjectClientTest extends TestCase
{
    private $endpoint = 'http://localhost:9000';
    private $key = 'test-key';
    private $secret = 'test-secret';
    private $bucket = 'test-bucket';

    public function testConstructor()
    {
        $client = new ObjectClient($this->endpoint, $this->key, $this->secret, $this->bucket);
        
        $this->assertInstanceOf(ObjectClient::class, $client);
    }

    public function testConstructorWithToken()
    {
        $token = 'test-token';
        $client = new ObjectClient($this->endpoint, $this->key, $this->secret, $this->bucket, $token);
        
        $this->assertInstanceOf(ObjectClient::class, $client);
    }

    public function testGetInstanceReturnsSameInstance()
    {
        $client1 = ObjectClient::getInstance($this->endpoint, $this->key, $this->secret, $this->bucket);
        $client2 = ObjectClient::getInstance($this->endpoint, $this->key, $this->secret, $this->bucket);
        
        $this->assertSame($client1, $client2);
    }

    public function testGetInstanceReturnsDifferentInstanceForDifferentParams()
    {
        $client1 = ObjectClient::getInstance($this->endpoint, $this->key, $this->secret, $this->bucket);
        $client2 = ObjectClient::getInstance($this->endpoint, $this->key, $this->secret, 'different-bucket');
        
        $this->assertNotSame($client1, $client2);
    }

    public function testGetInstanceWithToken()
    {
        $token = 'test-token';
        $client = ObjectClient::getInstance($this->endpoint, $this->key, $this->secret, $this->bucket, $token);
        
        $this->assertInstanceOf(ObjectClient::class, $client);
    }
}
