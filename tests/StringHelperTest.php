<?php

namespace hollisho\minio\tests;

use hollisho\minio\helpers\StringHelper;
use PHPUnit\Framework\TestCase;

class StringHelperTest extends TestCase
{
    public function testToGuidStringWithString()
    {
        $input = 'test string';
        $result = StringHelper::toGuidString($input);
        
        $this->assertIsString($result);
        $this->assertEquals(32, strlen($result));
        $this->assertEquals(md5(serialize($input)), $result);
    }

    public function testToGuidStringWithArray()
    {
        $input = ['key' => 'value', 'number' => 123];
        $result = StringHelper::toGuidString($input);
        
        $this->assertIsString($result);
        $this->assertEquals(32, strlen($result));
        $this->assertEquals(md5(serialize($input)), $result);
    }

    public function testToGuidStringWithObject()
    {
        $input = new \stdClass();
        $input->property = 'value';
        $result = StringHelper::toGuidString($input);
        
        $this->assertIsString($result);
        $this->assertEquals(spl_object_hash($input), $result);
    }

    public function testToGuidStringConsistency()
    {
        $input = 'consistent test';
        $result1 = StringHelper::toGuidString($input);
        $result2 = StringHelper::toGuidString($input);
        
        $this->assertEquals($result1, $result2);
    }

    public function testToGuidStringDifferentInputs()
    {
        $input1 = 'test1';
        $input2 = 'test2';
        $result1 = StringHelper::toGuidString($input1);
        $result2 = StringHelper::toGuidString($input2);
        
        $this->assertNotEquals($result1, $result2);
    }
}
