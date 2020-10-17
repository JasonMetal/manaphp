<?php

namespace Tests;

use ManaPHP\Di;
use ManaPHP\Dom\Document;
use ManaPHP\Dom\Selector;

class DomDocumentTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $di = new Di\FactoryDefault();
        $di->alias->set('@data', dirname(tmpfile()));
    }

    public function test_load()
    {
        $document = new Document();

        $this->assertNotEmpty($document->load('https://www.baidu.com')->getString());
        $this->assertNotEmpty($document->load(__DIR__ . '/Dom/sample.html')->getString());
        $this->assertEquals('<div></div>', $document->load('<div></div>')->getString());
    }

    public function test_loadFile()
    {
        $document = new Document();
        $this->assertNotEmpty($document->loadFile(__DIR__ . '/Dom/sample.html')->getString());
    }

    public function test_loadUrl()
    {
        $document = new Document();
        $this->assertNotEmpty($document->loadUrl('https://www.baidu.com')->getString());
    }

    public function test_loadString()
    {
        $document = new Document();
        $this->assertEquals('<div></div>', $document->loadString('<div></div>')->getString());
    }

    public function test_absolutizeUrl()
    {
        $body = <<<STR
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>base-href实验1：不设置base标签</title>
</head>
<body>
<a href="">这是一个href属性为空的链接</a>
<br>
<a href="http://www.baidu.com">这是一个href属性设为绝对路径(http://www.baidu.com)的链接</a>
<br>
<a href="test.html">这是一个href属性设置为相对路径（test.html）的链接</a>
    </body>
</html>
STR;
        $document = new Document();
        $document->loadString($body, 'http://www.baidu.com/about');
        $selector = new Selector($document);
        $links = $selector->find('a')->attr('href');
        $this->assertEquals('http://www.baidu.com/about', $document->absolutizeUrl($links['/html/body/a[1]']));
        $this->assertEquals('http://www.baidu.com', $document->absolutizeUrl($links['/html/body/a[2]']));
        $this->assertEquals('http://www.baidu.com/test.html', $document->absolutizeUrl($links['/html/body/a[3]']));


        $body = <<<STR
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>base-href实验1：不设置base标签</title>
    <base href="/">
</head>
<body>
<a href="">这是一个href属性为空的链接</a>
<br>
<a href="http://www.baidu.com">这是一个href属性设为绝对路径(http://www.baidu.com)的链接</a>
<br>
<a href="test.html">这是一个href属性设置为相对路径（test.html）的链接</a>
    </body>
</html>     
STR;
        $document = new Document();
        $document->loadString($body, 'http://www.baidu.com/about');
        $selector = new Selector($document);
        $links = $selector->find('a')->attr('href');
        $this->assertEquals('http://www.baidu.com/', $document->absolutizeUrl($links['/html/body/a[1]']));
        $this->assertEquals('http://www.baidu.com', $document->absolutizeUrl($links['/html/body/a[2]']));
        $this->assertEquals('http://www.baidu.com/test.html', $document->absolutizeUrl($links['/html/body/a[3]']));

        $body = <<<STR
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>base-href实验1：不设置base标签</title>
    <base href="localhost/test">
</head>
<body>
<a href="">这是一个href属性为空的链接</a>
<br>
<a href="http://www.baidu.com">这是一个href属性设为绝对路径(http://www.baidu.com)的链接</a>
<br>
<a href="test.html">这是一个href属性设置为相对路径（test.html）的链接</a>
    </body>
</html>
STR;
        $document = new Document();
        $document->loadString($body, 'http://www.baidu.com/about');
        $selector = new Selector($document);
        $links = $selector->find('a')->attr('href');
        $this->assertEquals('http://www.baidu.com/localhost/test', $document->absolutizeUrl($links['/html/body/a[1]']));
        $this->assertEquals('http://www.baidu.com', $document->absolutizeUrl($links['/html/body/a[2]']));
        $this->assertEquals(
            'http://www.baidu.com/localhost/test.html', $document->absolutizeUrl($links['/html/body/a[3]'])
        );

        $body = <<<STR
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>base-href实验4：base标签href属性为绝对路径</title>
    <base href="http://localhost/test/">
</head>
<body>
<a href="">这是一个href属性为空的链接</a>
<br>
<a href="http://www.baidu.com">这是一个href属性设为绝对路径(http://www.baidu.com)的链接</a>
<br>
<a href="test.html">这是一个href属性设置为相对路径（test.html）的链接</a>
    </body>
</html> 
STR;
        $document = new Document();
        $document->loadString($body, 'http://www.baidu.com/about');
        $selector = new Selector($document);
        $links = $selector->find('a')->attr('href');
        $this->assertEquals('http://localhost/test/', $document->absolutizeUrl($links['/html/body/a[1]']));
        $this->assertEquals('http://www.baidu.com', $document->absolutizeUrl($links['/html/body/a[2]']));
        $this->assertEquals('http://localhost/test/test.html', $document->absolutizeUrl($links['/html/body/a[3]']));
    }
}