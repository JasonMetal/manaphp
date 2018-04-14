<?php
namespace Tests;

use ManaPHP\Authentication\Token\Adapter\Jwt;
use ManaPHP\Di\FactoryDefault;
use PHPUnit\Framework\TestCase;

class  AuthenticationTokenAdapterJwtTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $di = new FactoryDefault();
        $di->crypt->setMasterKey('mwt_key');
    }

    public function test_encode()
    {
        $jwt = new Jwt();
        $decoded = $jwt->decode($jwt->encode(['id' => 100, 'name' => 'mana']));
        $this->assertEquals(100, $decoded['id']);
        $this->assertEquals('mana', $decoded['name']);
    }

    public function test_decode()
    {
        $jwt = new Jwt();
        $decoded = $jwt->decode($jwt->encode(['id' => 100, 'name' => 'mana']));
        $this->assertEquals(100, $decoded['id']);
        $this->assertEquals('mana', $decoded['name']);

        $jwt = new Jwt(['key' => '123456']);
        $decoded = $jwt->decode('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.keH6T3x1z7mmhKL1T3r9sQdAxxdzB6siemGMr_6ZOwU');
        $this->assertEquals('1234567890', $decoded['sub']);
        $this->assertEquals('John Doe', $decoded['name']);
        $this->assertEquals(1516239022, $decoded['iat']);
    }

    public function test_expire()
    {
        $jwt = new Jwt();
        $encoded = $jwt->encode(['id' => 100, 'name' => 'mana', 'exp' => 1]);
        sleep(2);
        $this->assertFalse($jwt->decode($encoded));
    }
}