<?php

namespace Smartling\Tests;

use Smartling\File\FileApi;
use Smartling\File\Params\DownloadFileParameters;
use Smartling\File\Params\UploadFileParameters;

/**
 * Test class for Smartling\File\FileApi.
 */
class SmartlingApiTest extends ApiTestAbstract
{
    private function prepareFileApiMock()
    {
        $this->object = $this->getMockBuilder('Smartling\File\FileApi')
            ->setMethods(['readFile'])
            ->setConstructorArgs([
                $this->projectId,
                $this->client,
                null,
                FileApi::ENDPOINT_URL,
            ])
            ->getMock();

        $this->object->expects(self::any())
            ->method('readFile')
            ->willReturn($this->streamPlaceholder);

        $this->invokeMethod(
            $this->object,
            'setAuth',
            [
                $this->authProvider
            ]
        );
    }

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->prepareFileApiMock();
    }

    /**
     * Tests constructor.
     *
     * @param string $projectId
     *   Project Id string.
     * @param \GuzzleHttp\ClientInterface $client
     *   Mock of Guzzle http client instance.
     * @param string|null $expected_base_url
     *   Base Url string that will be used as based url.
     *
     * @covers       \Smartling\File\FileApi::__construct
     *
     * @dataProvider constructorDataProvider
     */
    public function testConstructor($projectId, $client, $expected_base_url)
    {
        $fileApi = new FileApi($projectId, $client, null, $expected_base_url);

        self::assertEquals(rtrim($expected_base_url, '/') . '/' . $projectId,
            $this->invokeMethod($fileApi, 'getBaseUrl'));
        self::assertEquals($projectId, $this->invokeMethod($fileApi, 'getProjectId'));
        self::assertEquals($client, $this->invokeMethod($fileApi, 'getHttpClient'));
    }

    /**
     * Data provider for testConstructor method.
     *
     * Tests if base url will be set correctly depending on income baseurl
     * and mode.
     *
     * @return array
     */
    public function constructorDataProvider()
    {
        $this->prepareHttpClientMock();

        $mockedClient = $this->client;

        return [
            ['product-id', $mockedClient, FileApi::ENDPOINT_URL],
            ['product-id', $mockedClient, FileApi::ENDPOINT_URL],
            ['product-id', $mockedClient, FileApi::ENDPOINT_URL . '/'],
            ['product-id', $mockedClient, 'https://www.google.com.ua/webhp'],
        ];
    }

    /**
     * @covers \Smartling\File\FileApi::uploadFile
     */
    public function testUploadFile()
    {
        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with('post', FileApi::ENDPOINT_URL . '/' . $this->projectId . '/file', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => vsprintf(' %s %s', [
                        $this->authProvider->getTokenType(),
                        $this->authProvider->getAccessToken(),
                    ]),
                ],
                'exceptions' => false,
                'body' => [
                    'smartling.client_lib_id' =>
                        json_encode(
                            [
                                'client' => UploadFileParameters::CLIENT_LIB_ID_SDK,
                                'version' => UploadFileParameters::CLIENT_LIB_ID_VERSION,
                            ],
                            JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE
                        ),
                    'authorize' => '0',
                    'localeIdsToAuthorize' => ['es'],
                    'file' => $this->streamPlaceholder,
                    'fileUri' => 'test.xml',
                    'fileType' => 'xml'
                ],
            ])
            ->willReturn($this->requestMock);

        $this->client
            ->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $params = new UploadFileParameters();
        $params->setAuthorized(true);
        $params->setLocalesToApprove(['es']);

        $this->object->uploadFile('tests/resources/test.xml', 'test.xml', 'xml', $params);
    }

    /**
     * @covers       \Smartling\File\FileApi::downloadFile
     *
     * @dataProvider downloadFileParams
     *
     * @param DownloadFileParameters|null $options
     * @param string $expected_translated_file
     */
    public function testDownloadFile($options, $expected_translated_file)
    {
        $this->prepareClientResponseMock(false);

        $this->responseMock->expects(self::any())
            ->method('getBody')
            ->willReturn($expected_translated_file);

        $endpointUrl = vsprintf(
            '%s/%s/locales/%s/file',
            [
                FileApi::ENDPOINT_URL,
                $this->projectId,
                'en-EN'
            ]
        );

        $params = $options instanceof DownloadFileParameters
            ? $options->exportToArray()
            : [];

        $params['fileUri'] = 'test.xml';

        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with('get', $endpointUrl, [
                'headers' => [
                    'Authorization' => vsprintf(' %s %s', [
                        $this->authProvider->getTokenType(),
                        $this->authProvider->getAccessToken(),
                    ]),
                ],
                'exceptions' => false,
                'query' => $params,
            ])
            ->willReturn($this->requestMock);

        $this->client
            ->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $actual_xml = $this->object->downloadFile('test.xml', 'en-EN', $options);

        self::assertEquals($expected_translated_file, $actual_xml);
    }

    public function downloadFileParams()
    {
        return [
            [
                (new DownloadFileParameters())->setRetrievalType(DownloadFileParameters::RETRIEVAL_TYPE_PSEUDO),
                '<?xml version="1.0"?><response><item key="6"></item></response>'
            ],
            [
                null,
                '<?xml version="1.0"?><response><item key="6"></item></response>'
            ],
            [
                null,
                '{"string1":"translation1", "string2":"translation2"}'
            ],
        ];
    }

    /**
     * @covers \Smartling\File\FileApi::getStatus
     */
    public function testGetStatus()
    {
        $endpointUrl = vsprintf(
            '%s/%s/locales/%s/file/status',
            [
                FileApi::ENDPOINT_URL,
                $this->projectId,
                'en-EN'
            ]
        );

        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with('get', $endpointUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => vsprintf(' %s %s', [
                        $this->authProvider->getTokenType(),
                        $this->authProvider->getAccessToken(),
                    ]),
                ],
                'exceptions' => false,
                'query' => [
                    'fileUri' => 'test.xml',
                ],
            ])
            ->willReturn($this->requestMock);

        $this->client->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $this->object->getStatus('test.xml', 'en-EN');
    }

    /**
     * @covers \Smartling\File\FileApi::getList
     */
    public function testGetList()
    {
        $endpointUrl = vsprintf(
            '%s/%s/files/list',
            [
                FileApi::ENDPOINT_URL,
                $this->projectId
            ]
        );

        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with('get', $endpointUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => vsprintf(' %s %s', [
                        $this->authProvider->getTokenType(),
                        $this->authProvider->getAccessToken(),
                    ]),
                ],
                'exceptions' => false,
                'query' => [],
            ])
            ->willReturn($this->requestMock);

        $this->client->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $this->object->getList();
    }

    /**
     * @covers \Smartling\File\FileApi::sendRequest
     * @expectedException \Smartling\Exceptions\SmartlingApiException
     * @expectedExceptionMessage Validation error text
     */
    public function testValidationErrorSendRequest()
    {
        $this->prepareClientResponseMock(false);

        $this->responseMock->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(400);
        $this->responseMock->expects(self::any())
            ->method('getBody')
            ->willReturn($this->responseWithException);
        $this->responseMock->expects(self::any())
            ->method('json')
            ->willReturn(json_decode($this->responseWithException, JSON_OBJECT_AS_ARRAY));

        $endpointUrl = vsprintf(
            '%s/%s/context/html',
            [
                FileApi::ENDPOINT_URL,
                $this->projectId
            ]
        );

        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with('get', $endpointUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => vsprintf(' %s %s', [
                        $this->authProvider->getTokenType(),
                        $this->authProvider->getAccessToken(),
                    ]),
                ],
                'exceptions' => false,
                'query' => [],
            ])
            ->willReturn($this->requestMock);

        $this->client->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $this->invokeMethod($this->object, 'setBaseUrl', [FileApi::ENDPOINT_URL . '/' . $this->projectId]);
        $this->invokeMethod($this->object, 'sendRequest', ['context/html', [], 'get']);
    }

    /**
     * @covers \Smartling\File\FileApi::sendRequest
     * @expectedException \Smartling\Exceptions\SmartlingApiException
     * @expectedExceptionMessage Bad response format from Smartling
     */
    public function testBadJsonFormatSendRequest()
    {
        $this->prepareClientResponseMock(false);

        $this->responseMock->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(400);
        $this->responseMock->expects(self::any())
            ->method('getBody')
            ->willReturn(rtrim($this->responseWithException, '}'));
        $this->responseMock->expects(self::any())
            ->method('json')
            ->willThrowException(new \RuntimeException(''));

        $endpointUrl = vsprintf(
            '%s/%s/context/html',
            [
                FileApi::ENDPOINT_URL,
                $this->projectId
            ]
        );

        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with('get', $endpointUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => vsprintf(' %s %s', [
                        $this->authProvider->getTokenType(),
                        $this->authProvider->getAccessToken(),
                    ]),
                ],
                'exceptions' => false,
                'query' => [],
            ])
            ->willReturn($this->requestMock);

        $this->client->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $this->invokeMethod($this->object, 'setBaseUrl', [FileApi::ENDPOINT_URL . '/' . $this->projectId]);
        $this->invokeMethod($this->object, 'sendRequest', ['context/html', [], 'get']);
    }

    /**
     * @covers \Smartling\File\FileApi::sendRequest
     * @expectedException \Smartling\Exceptions\SmartlingApiException
     * @expectedExceptionMessage Bad response format from Smartling
     */
    public function testBadJsonFormatInErrorMessageSendRequest()
    {
        $this->prepareClientResponseMock(false);

        $this->responseMock->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(401);
        $this->responseMock->expects(self::any())
            ->method('getBody')
            ->willReturn(rtrim($this->responseWithException, '}'));
        $this->responseMock->expects(self::any())
            ->method('json')
            ->willThrowException(new \RuntimeException(''));

        $endpointUrl = vsprintf(
            '%s/%s/context/html',
            [
                FileApi::ENDPOINT_URL,
                $this->projectId
            ]
        );

        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with('get', $endpointUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => vsprintf(' %s %s', [
                        $this->authProvider->getTokenType(),
                        $this->authProvider->getAccessToken(),
                    ]),
                ],
                'exceptions' => false,
                'query' => [],
            ])
            ->willReturn($this->requestMock);

        $this->client->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $this->invokeMethod($this->object, 'setBaseUrl', [FileApi::ENDPOINT_URL . '/' . $this->projectId]);
        $this->invokeMethod($this->object, 'sendRequest', ['context/html', [], 'get']);
    }

    /**
     * @param string $uri
     * @param array $requestData
     * @param string $method
     * @param array $params
     *
     * @covers       \Smartling\File\FileApi::sendRequest
     * @dataProvider sendRequestValidProvider
     */
    public function testSendRequest($uri, $requestData, $method, $params)
    {
        $params['headers']['Authorization'] = vsprintf(' %s %s', [
            $this->authProvider->getTokenType(),
            $this->authProvider->getAccessToken(),
        ]);

        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with($method, FileApi::ENDPOINT_URL . '/' . $this->projectId . '/' . $uri, $params)
            ->willReturn($this->requestMock);


        $this->client->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $this->invokeMethod($this->object, 'setBaseUrl', [FileApi::ENDPOINT_URL . '/' . $this->projectId]);

        $result = $this->invokeMethod($this->object, 'sendRequest', [$uri, $requestData, $method]);
        self::assertEquals(['wordCount' => 1629, 'stringCount' => 503, 'overWritten' => false], $result);
    }

    /**
     * Data provider callback for testSendRequest method.
     *
     * @return array
     */
    public function sendRequestValidProvider()
    {
        return [
            [
                'uri',
                [],
                'get',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'exceptions' => false,
                    'query' => [],
                ],
            ],
            [
                'uri',
                [
                    'key' => 'value',
                    'boolean_false' => false,
                    'boolean_true' => true,
                    'file' => './tests/resources/test.xml',
                ],
                'post',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'exceptions' => false,
                    'body' => [
                        'file' => $this->streamPlaceholder,
                        'key' => 'value',
                        'boolean_false' => '0',
                        'boolean_true' => '1',
                    ],
                ],
            ],
        ];
    }

    /**
     * @covers \Smartling\File\FileApi::renameFile
     */
    public function testRenameFile()
    {
        $endpointUrl = vsprintf(
            '%s/%s/file/rename',
            [
                FileApi::ENDPOINT_URL,
                $this->projectId
            ]
        );

        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with('post', $endpointUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => vsprintf(' %s %s', [
                        $this->authProvider->getTokenType(),
                        $this->authProvider->getAccessToken(),
                    ]),
                ],
                'exceptions' => false,
                'body' => [
                    'fileUri' => 'test.xml',
                    'newFileUri' => 'new_test.xml',
                ],
            ])
            ->willReturn($this->requestMock);

        $this->client->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $this->object->renameFile('test.xml', 'new_test.xml');
    }

    /**
     * @covers \Smartling\File\FileApi::getAuthorizedLocales
     */
    public function testGetAuthorizedLocales()
    {
        $endpointUrl = vsprintf(
            '%s/%s/file/authorized-locales',
            [
                FileApi::ENDPOINT_URL,
                $this->projectId
            ]
        );

        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with('get', $endpointUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => vsprintf(' %s %s', [
                        $this->authProvider->getTokenType(),
                        $this->authProvider->getAccessToken(),
                    ]),
                ],
                'exceptions' => false,
                'query' => [
                    'fileUri' => 'test.xml',
                ],
            ])
            ->willReturn($this->requestMock);

        $this->client->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $this->object->getAuthorizedLocales('test.xml');
    }

    /**
     * @covers \Smartling\File\FileApi::deleteFile
     */
    public function testDeleteFile()
    {
        $endpointUrl = vsprintf('%s/%s/file/delete', [FileApi::ENDPOINT_URL, $this->projectId]);

        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with('post', $endpointUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => vsprintf(' %s %s', [
                        $this->authProvider->getTokenType(),
                        $this->authProvider->getAccessToken(),
                    ]),
                ],
                'exceptions' => false,
                'body' => [
                    'fileUri' => 'test.xml',

                ],
            ])
            ->willReturn($this->requestMock);

        $this->client->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $this->object->deleteFile('test.xml');
    }

    /**
     * @covers \Smartling\File\FileApi::import
     */
    public function testImport()
    {
        $locale = 'en-EN';
        $endpointUrl = vsprintf(
            '%s/%s/locales/%s/file/import',
            [
                FileApi::ENDPOINT_URL,
                $this->projectId,
                $locale
            ]
        );

        $this->client
            ->expects(self::any())
            ->method('createRequest')
            ->with('post', $endpointUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => vsprintf(' %s %s', [
                        $this->authProvider->getTokenType(),
                        $this->authProvider->getAccessToken(),
                    ]),
                ],
                'exceptions' => false,
                'body' => [
                    'file' => $this->streamPlaceholder,
                    'fileUri' => 'test.xml',
                    'fileType' => 'xml',
                    'translationState' => 'PUBLISHED',
                    'overwrite' => '0',

                ],
            ])
            ->willReturn($this->requestMock);

        $this->client->expects(self::once())
            ->method('send')
            ->with($this->requestMock)
            ->willReturn($this->responseMock);

        $this->object->import(
            $locale,
            'test.xml',
            'xml',
            'tests/resources/test.xml',
            'PUBLISHED',
            false
        );
    }

    /**
     * @covers \Smartling\File\FileApi::readFile
     */
    public function testReadFile()
    {

        $validFilePath = './tests/resources/test.xml';

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\Smartling\File\FileApi
         */
        $fileApi = $this->getMockBuilder('Smartling\\File\\FileApi')
            ->setConstructorArgs([$this->projectId, $this->client])
            ->getMock();

        $stream = $this->invokeMethod($fileApi, 'readFile', [$validFilePath]);

        self::assertEquals('stream', get_resource_type($stream));
    }

    /**
     * @covers \Smartling\File\FileApi::readFile
     *
     * @expectedException \Smartling\Exceptions\SmartlingApiException
     * @expectedExceptionMessage File unexisted was not able to be read.
     */
    public function testFailedReadFile()
    {
        $invalidFilePath = 'unexisted';

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\Smartling\File\FileApi
         */
        $fileApi = $this->getMockBuilder('Smartling\\File\\FileApi')
            ->setConstructorArgs([$this->projectId, $this->client])
            ->getMock();

        $stream = $this->invokeMethod($fileApi, 'readFile', [$invalidFilePath]);

        self::assertEquals('stream', get_resource_type($stream));
    }
}
