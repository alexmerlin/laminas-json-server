<?php

/**
 * @see       https://github.com/laminas/laminas-json-server for the canonical source repository
 * @copyright https://github.com/laminas/laminas-json-server/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-json-server/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Json\Server;

use Laminas\Json\Json;
use Laminas\Json\Server\Error;
use Laminas\Json\Server\Exception\RuntimeException;
use Laminas\Json\Server\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    private $response;
    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @return void
     */
    public function setUp()
    {
        $this->response = new Response();
    }

    public function testResultShouldBeNullByDefault()
    {
        $this->assertNull($this->response->getResult());
    }

    public function testResultAccessorsShouldWorkWithNormalInput()
    {
        foreach ([true, 'foo', 2, 2.0, [], ['foo' => 'bar']] as $result) {
            $this->response->setResult($result);
            $this->assertEquals($result, $this->response->getResult());
        }
    }

    public function testResultShouldNotBeErrorByDefault()
    {
        $this->assertFalse($this->response->isError());
    }

    public function testSettingErrorShouldMarkRequestAsError()
    {
        $error = new Error();
        $this->response->setError($error);
        $this->assertTrue($this->response->isError());
    }

    public function testShouldBeAbleToRetrieveErrorObject()
    {
        $error = new Error();
        $this->response->setError($error);
        $this->assertSame($error, $this->response->getError());
    }

    public function testErrorAccesorsShouldWorkWithNullInput()
    {
        $this->response->setError(null);
        $this->assertNull($this->response->getError());
        $this->assertFalse($this->response->isError());
    }

    public function testIdShouldBeNullByDefault()
    {
        $this->assertNull($this->response->getId());
    }

    public function testIdAccesorsShouldWorkWithNormalInput()
    {
        $this->response->setId('foo');
        $this->assertEquals('foo', $this->response->getId());
    }

    public function testVersionShouldBeNullByDefault()
    {
        $this->assertNull($this->response->getVersion());
    }

    public function testVersionShouldBeLimitedToV2()
    {
        $this->response->setVersion('2.0');
        $this->assertEquals('2.0', $this->response->getVersion());
        foreach (['a', 1, '1.0', true] as $version) {
            $this->response->setVersion($version);
            $this->assertNull($this->response->getVersion());
        }
    }

    public function testShouldBeAbleToLoadResponseFromJSONString()
    {
        $options = $this->getOptions();
        $json    = Json::encode($options);
        $this->response->loadJSON($json);

        $this->assertEquals('foobar', $this->response->getId());
        $this->assertEquals($options['result'], $this->response->getResult());
    }

    public function testLoadingFromJSONShouldSetJSONRpcVersionWhenPresent()
    {
        $options = $this->getOptions();
        $options['jsonrpc'] = '2.0';
        $json    = Json::encode($options);
        $this->response->loadJSON($json);
        $this->assertEquals('2.0', $this->response->getVersion());
    }

    public function testResponseShouldBeAbleToCastToJSON()
    {
        $this->response->setResult(true)
                       ->setId('foo')
                       ->setVersion('2.0');
        $json = $this->response->toJSON();
        $test = Json::decode($json, Json::TYPE_ARRAY);

        $this->assertInternalType('array', $test);
        $this->assertArrayHasKey('result', $test);
        $this->assertArrayNotHasKey('error', $test, "'error' may not coexist with 'result'");
        $this->assertArrayHasKey('id', $test);
        $this->assertArrayHasKey('jsonrpc', $test);

        $this->assertTrue($test['result']);
        $this->assertEquals($this->response->getId(), $test['id']);
        $this->assertEquals($this->response->getVersion(), $test['jsonrpc']);
    }

    public function testResponseShouldCastErrorToJSONIfIsError()
    {
        $error = new Error();
        $error->setCode(Error::ERROR_INTERNAL)
              ->setMessage('error occurred');
        $this->response->setId('foo')
                       ->setResult(true)
                       ->setError($error);
        $json = $this->response->toJSON();
        $test = Json::decode($json, Json::TYPE_ARRAY);

        $this->assertInternalType('array', $test);
        $this->assertArrayNotHasKey('result', $test, "'result' may not coexist with 'error'");
        $this->assertArrayHasKey('error', $test);
        $this->assertArrayHasKey('id', $test);
        $this->assertArrayNotHasKey('jsonrpc', $test);

        $this->assertEquals($this->response->getId(), $test['id']);
        $this->assertEquals($error->getCode(), $test['error']['code']);
        $this->assertEquals($error->getMessage(), $test['error']['message']);
    }

    public function testCastToStringShouldCastToJSON()
    {
        $this->response->setResult(true)
                       ->setId('foo');
        $json = $this->response->__toString();
        $test = Json::decode($json, Json::TYPE_ARRAY);

        $this->assertInternalType('array', $test);
        $this->assertArrayHasKey('result', $test);
        $this->assertArrayNotHasKey('error', $test, "'error' may not coexist with 'result'");
        $this->assertArrayHasKey('id', $test);
        $this->assertArrayNotHasKey('jsonrpc', $test);

        $this->assertTrue($test['result']);
        $this->assertEquals($this->response->getId(), $test['id']);
    }

    /**
     * @param string $json
     *
     * @group 5956
     *
     * @dataProvider provideScalarJSONResponses
     */
    public function testLoadingScalarJSONResponseShouldThrowException($json)
    {
        $this->expectException(RuntimeException::class);
        $this->response->loadJson($json);
    }

    /**
     * @return string[][]
     */
    public function provideScalarJSONResponses()
    {
        return [[''], ['true'], ['null'], ['3'], ['"invalid"']];
    }

    public function getOptions()
    {
        return [
            'result' => [
                5,
                'four',
                true,
            ],
            'id'  => 'foobar',
        ];
    }

    /**
     * @see https://github.com/zendframework/zend-json-server/pull/2
     */
    public function testValueOfZeroForOptionsKeyShouldNotBeInterpretedAsVersionKey()
    {
        $this->response->setOptions([
            0 => '2.0',
        ]);
        $this->assertNull($this->response->getVersion());
    }

    /**
     * Assert that error data can be omitted
     * @see https://www.jsonrpc.org/specification#response_object
     */
    public function testSetOptionsAcceptsErrorWithEmptyDate()
    {
        $this->response->setOptions([
            'error' => [
                'code' => 0,
                'message' => 'Lorem Ipsum',
            ],
        ]);
        $this->assertInstanceOf(Error::class, $this->response->getError());
        $this->assertEquals(-32000, $this->response->getError()->getCode());
        $this->assertEquals('Lorem Ipsum', $this->response->getError()->getMessage());
        $this->assertEquals(null, $this->response->getError()->getData());
    }
}
