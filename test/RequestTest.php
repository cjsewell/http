<?php
namespace PhlyTest\Http;

use Phly\Http\Request;
use Phly\Http\Stream;
use Phly\Http\Uri;
use PHPUnit_Framework_TestCase as TestCase;

class RequestTest extends TestCase
{
    public function setUp()
    {
        $this->request = new Request();
    }

    public function testMethodIsNullByDefault()
    {
        $this->assertNull($this->request->getMethod());
    }

    public function testMethodMutatorReturnsCloneWithChangedMethod()
    {
        $request = $this->request->withMethod('GET');
        $this->assertNotSame($this->request, $request);
        $this->assertEquals('GET', $request->getMethod());
    }

    public function testUriIsNullByDefault()
    {
        $this->assertNull($this->request->getUri());
    }

    public function testConstructorRaisesExceptionForInvalidStream()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Request(['TOTALLY INVALID']);
    }

    public function invalidUrls()
    {
        return [
            'null'   => [null],
            'true'   => [true],
            'false'  => [false],
            'int'    => [1],
            'float'  => [1.1],
            'array'  => [['foo']],
            'object' => [(object) ['foo']],
        ];
    }

    public function testWithUriReturnsNewInstanceWithNewUri()
    {
        $request = $this->request->withUri(new Uri('https://example.com:10082/foo/bar?baz=bat'));
        $this->assertNotSame($this->request, $request);
        $request2 = $request->withUri(new Uri('/baz/bat?foo=bar'));
        $this->assertNotSame($this->request, $request2);
        $this->assertNotSame($request, $request2);
        $this->assertEquals('/baz/bat?foo=bar', (string) $request2->getUri());
    }

    public function testConstructorCanAcceptAllMessageParts()
    {
        $uri     = new Uri('http://example.com/');
        $body    = new Stream('php://memory');
        $headers = [
            'x-foo' => ['bar'],
        ];
        $request = new Request(
            $uri,
            'POST',
            $body,
            $headers
        );

        $this->assertSame($uri, $request->getUri());
        $this->assertEquals('POST', $request->getMethod());
        $this->assertSame($body, $request->getBody());
        $testHeaders = $request->getHeaders();
        foreach ($headers as $key => $value) {
            $this->assertArrayHasKey($key, $testHeaders);
            $this->assertEquals($value, $testHeaders[$key]);
        }
    }

    public function invalidRequestUri()
    {
        return [
            'true'     => [ true ],
            'false'    => [ false ],
            'int'      => [ 1 ],
            'float'    => [ 1.1 ],
            'array'    => [ ['http://example.com'] ],
            'stdClass' => [ (object) [ 'href'         => 'http://example.com'] ],
        ];
    }

    /**
     * @dataProvider invalidRequestUri
     */
    public function testConstructorRaisesExceptionForInvalidUri($uri)
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid URI');
        new Request($uri);
    }

    public function invalidRequestMethod()
    {
        return [
            'true'       => [ true ],
            'false'      => [ false ],
            'int'        => [ 1 ],
            'float'      => [ 1.1 ],
            'bad-string' => [ 'BOGUS-METHOD' ],
            'array'      => [ ['POST'] ],
            'stdClass'   => [ (object) [ 'method' => 'POST'] ],
        ];
    }

    /**
     * @dataProvider invalidRequestMethod
     */
    public function testConstructorRaisesExceptionForInvalidMethod($method)
    {
        $this->setExpectedException('InvalidArgumentException', 'Unsupported HTTP method');
        new Request(null, $method);
    }

    public function invalidRequestBody()
    {
        return [
            'true'       => [ true ],
            'false'      => [ false ],
            'int'        => [ 1 ],
            'float'      => [ 1.1 ],
            'array'      => [ ['BODY'] ],
            'stdClass'   => [ (object) [ 'body' => 'BODY'] ],
        ];
    }

    /**
     * @dataProvider invalidRequestBody
     */
    public function testConstructorRaisesExceptionForInvalidBody($body)
    {
        $this->setExpectedException('InvalidArgumentException', 'stream');
        new Request(null, null, $body);
    }

    public function testConstructorIgonoresInvalidHeaders()
    {
        $headers = [
            [ 'INVALID' ],
            'x-invalid-null' => null,
            'x-invalid-true' => true,
            'x-invalid-false' => false,
            'x-invalid-int' => 1,
            'x-invalid-object' => (object) ['INVALID'],
            'x-valid-string' => 'VALID',
            'x-valid-array' => [ 'VALID' ],
        ];
        $expected = [
            'x-valid-string' => [ 'VALID' ],
            'x-valid-array' => [ 'VALID' ],
        ];
        $request = new Request(null, null, 'php://memory', $headers);
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testRequestTargetIsSlashWhenNoUriPresent()
    {
        $request = new Request();
        $this->assertEquals('/', $request->getRequestTarget());
    }

    public function testRequestTargetIsSlashWhenUriHasNoPathOrQuery()
    {
        $request = (new Request())
            ->withUri(new Uri('http://example.com'));
        $this->assertEquals('/', $request->getRequestTarget());
    }

    public function requestsWithUri()
    {
        return [
            'absolute-uri' => [
                (new Request())
                ->withUri(new Uri('https://api.example.com/user'))
                ->withMethod('POST'),
                '/user'
            ],
            'absolute-uri-with-query' => [
                (new Request())
                ->withUri(new Uri('https://api.example.com/user?foo=bar'))
                ->withMethod('POST'),
                '/user?foo=bar'
            ],
            'relative-uri' => [
                (new Request())
                ->withUri(new Uri('/user'))
                ->withMethod('GET'),
                '/user'
            ],
            'relative-uri-with-query' => [
                (new Request())
                ->withUri(new Uri('/user?foo=bar'))
                ->withMethod('GET'),
                '/user?foo=bar'
            ],
        ];
    }

    /**
     * @dataProvider requestsWithUri
     */
    public function testReturnsRequestTargetWhenUriIsPresent($request, $expected)
    {
        $this->assertEquals($expected, $request->getRequestTarget());
    }

    public function validRequestTargets()
    {
        return [
            'asterisk-form'         => [ '*' ],
            'authority-form'        => [ 'api.example.com' ],
            'absolute-form'         => [ 'https://api.example.com/users' ],
            'absolute-form-query'   => [ 'https://api.example.com/users?foo=bar' ],
            'origin-form-path-only' => [ '/users' ],
            'origin-form'           => [ '/users?id=foo' ],
        ];
    }

    /**
     * @dataProvider validRequestTargets
     */
    public function testCanProvideARequestTarget($requestTarget)
    {
        $request = (new Request())->withRequestTarget($requestTarget);
        $this->assertEquals($requestTarget, $request->getRequestTarget());
    }

    public function testRequestTargetCannotContainWhitespace()
    {
        $request = new Request();
        $this->setExpectedException('InvalidArgumentException', 'Invalid request target');
        $request->withRequestTarget('foo bar baz');
    }

    public function testRequestTargetDoesNotCacheBetweenInstances()
    {
        $request = (new Request())->withUri(new Uri('https://example.com/foo/bar'));
        $original = $request->getRequestTarget();
        $newRequest = $request->withUri(new Uri('http://mwop.net/bar/baz'));
        $this->assertNotEquals($original, $newRequest->getRequestTarget());
    }

    public function testSettingNewUriResetsRequestTarget()
    {
        $request = (new Request())->withUri(new Uri('https://example.com/foo/bar'));
        $original = $request->getRequestTarget();
        $newRequest = $request->withUri(new Uri('http://mwop.net/bar/baz'));
    }

    /**
     * @group 39
     */
    public function testGetHeadersContainsHostHeaderIfUriWithHostIsPresent()
    {
        $request = new Request('http://example.com');
        $headers = $request->getHeaders();
        $this->assertArrayHasKey('Host', $headers);
        $this->assertContains('example.com', $headers['Host']);
    }

    /**
     * @group 39
     */
    public function testGetHeadersContainsNoHostHeaderIfNoUriPresent()
    {
        $request = new Request();
        $headers = $request->getHeaders();
        $this->assertArrayNotHasKey('Host', $headers);
    }

    /**
     * @group 39
     */
    public function testGetHeadersContainsNoHostHeaderIfUriDoesNotContainHost()
    {
        $request = new Request(new Uri());
        $headers = $request->getHeaders();
        $this->assertArrayNotHasKey('Host', $headers);
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderReturnsUriHostWhenPresent()
    {
        $request = new Request('http://example.com');
        $header = $request->getHeader('host');
        $this->assertEquals(array('example.com'), $header);
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderReturnsEmptyArrayIfNoUriPresent()
    {
        $request = new Request();
        $this->assertSame([], $request->getHeader('host'));
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderReturnsEmptyArrayIfUriDoesNotContainHost()
    {
        $request = new Request(new Uri());
        $this->assertSame([], $request->getHeader('host'));
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderLineReturnsUriHostWhenPresent()
    {
        $request = new Request('http://example.com');
        $header = $request->getHeaderLine('host');
        $this->assertContains('example.com', $header);
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderLineReturnsNullIfNoUriPresent()
    {
        $request = new Request();
        $this->assertNull($request->getHeaderLine('host'));
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderLineReturnsNullIfUriDoesNotContainHost()
    {
        $request = new Request(new Uri());
        $this->assertNull($request->getHeaderLine('host'));
    }

    public function testPassingPreserveHostFlagWhenUpdatingUriDoesNotUpdateHostHeader()
    {
        $request = (new Request())
            ->withAddedHeader('Host', 'example.com');

        $uri = (new Uri())->withHost('www.example.com');
        $new = $request->withUri($uri, true);

        $this->assertEquals('example.com', $new->getHeaderLine('Host'));
    }

    public function testNotPassingPreserveHostFlagWhenUpdatingUriWithoutHostDoesNotUpdateHostHeader()
    {
        $request = (new Request())
            ->withAddedHeader('Host', 'example.com');

        $uri = new Uri();
        $new = $request->withUri($uri);

        $this->assertEquals('example.com', $new->getHeaderLine('Host'));
    }

    public function testHostHeaderUpdatesToUriHostAndPortWhenPreserveHostDisabledAndNonStandardPort()
    {
        $request = (new Request())
            ->withAddedHeader('Host', 'example.com');

        $uri = (new Uri())
            ->withHost('www.example.com')
            ->withPort(10081);
        $new = $request->withUri($uri);

        $this->assertEquals('www.example.com:10081', $new->getHeaderLine('Host'));
    }

    public function headersWithInjectionVectors()
    {
        return [
            'name-with-cr'           => ["X-Foo\r-Bar", 'value'],
            'name-with-lf'           => ["X-Foo\n-Bar", 'value'],
            'name-with-crlf'         => ["X-Foo\r\n-Bar", 'value'],
            'name-with-2crlf'        => ["X-Foo\r\n\r\n-Bar", 'value'],
            'value-with-cr'          => ['X-Foo-Bar', "value\rinjection"],
            'value-with-lf'          => ['X-Foo-Bar', "value\ninjection"],
            'value-with-crlf'        => ['X-Foo-Bar', "value\r\ninjection"],
            'value-with-2crlf'       => ['X-Foo-Bar', "value\r\n\r\ninjection"],
            'array-value-with-cr'    => ['X-Foo-Bar', ["value\rinjection"]],
            'array-value-with-lf'    => ['X-Foo-Bar', ["value\ninjection"]],
            'array-value-with-crlf'  => ['X-Foo-Bar', ["value\r\ninjection"]],
            'array-value-with-2crlf' => ['X-Foo-Bar', ["value\r\n\r\ninjection"]],
        ];
    }

    /**
     * @group ZF2015-04
     * @dataProvider headersWithInjectionVectors
     */
    public function testConstructorRaisesExceptionForHeadersWithCRLFVectors($name, $value)
    {
        $this->setExpectedException('InvalidArgumentException');
        $request = new Request(null, null, 'php://memory', [$name =>  $value]);
    }
}
