<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use CURLStringFile;
use PHPUnit\Framework\TestCase;
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Exceptions\NetworkException;
use TGbotPHP\Http\CurlTransport;
use TGbotPHP\Support\Value;
use TGbotPHP\Tests\Support\RuntimeControls;

/**
 * Runs against PHP's built-in server, started for this test class
 */
final class CurlTransportTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;

    private static string $baseUrl;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        $port = self::freePort();
        self::$baseUrl = "http://127.0.0.1:$port";

        $server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", dirname(__DIR__) . '/Support/Server/router.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        );
        self::assertIsResource($server);
        self::$server = $server;

        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', $port);
            if ($socket !== false) {
                fclose($socket);
                return;
            }
            usleep(50_000);
        }

        self::fail('The test server did not start');
    }

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    public function testPostsUrlEncodedFields(): void
    {
        $response = new CurlTransport()->post(self::$baseUrl . '/echo', ['text' => 'ciao & é', 'n' => '1'], false, 5);
        $echo = self::decode($response->body);

        self::assertSame(200, $response->statusCode);
        self::assertSame('POST', $echo['method']);
        self::assertSame('application/x-www-form-urlencoded', $echo['content_type']);
        self::assertSame('TGbotPHP/' . ApiClient::VERSION, $echo['user_agent']);
        self::assertSame(['text' => 'ciao & é', 'n' => '1'], $echo['post']);
    }

    public function testPostsMultipartFiles(): void
    {
        $response = new CurlTransport()->post(
            self::$baseUrl . '/echo',
            ['chat_id' => '1', 'document' => new CURLStringFile('file-body', 'notes.txt', 'text/plain')],
            true,
            5,
        );
        $echo = self::decode($response->body);

        self::assertStringStartsWith('multipart/form-data; boundary=', Value::string($echo['content_type']));
        self::assertSame(['chat_id' => '1'], $echo['post']);
        self::assertSame(['document' => ['name' => 'notes.txt', 'contents' => 'file-body']], $echo['files']);
    }

    public function testReusesTheTransportForSeveralRequests(): void
    {
        $transport = new CurlTransport();

        self::assertSame(404, $transport->get(self::$baseUrl . '/missing', 5)->statusCode);
        self::assertSame(200, $transport->post(self::$baseUrl . '/echo', ['a' => '1'], false, 5)->statusCode);
        self::assertSame('GET', self::decode($transport->get(self::$baseUrl . '/echo', 5)->body)['method']);
    }

    public function testDoesNotFollowRedirects(): void
    {
        $response = new CurlTransport()->get(self::$baseUrl . '/redirect', 5);

        self::assertSame(302, $response->statusCode);
        self::assertSame('', $response->body);
    }

    public function testDownloadsToFile(): void
    {
        $destination = tempnam(sys_get_temp_dir(), 'tgbotphp');
        self::assertIsString($destination);

        try {
            self::assertSame(200, new CurlTransport()->download(self::$baseUrl . '/file', $destination, 5));
            self::assertSame(1_000_000, filesize($destination));
        } finally {
            unlink($destination);
        }
    }

    public function testTimeoutThrowsNetworkException(): void
    {
        $this->expectException(NetworkException::class);

        new CurlTransport()->get(self::$baseUrl . '/slow', 1);
    }

    public function testConnectionErrorThrowsNetworkException(): void
    {
        $this->expectException(NetworkException::class);

        new CurlTransport(connectTimeout: 1)->get('http://127.0.0.1:' . self::freePort() . '/', 1);
    }

    public function testRejectsOtherProtocols(): void
    {
        $this->expectException(NetworkException::class);

        new CurlTransport()->get('file:///etc/passwd', 1);
    }

    public function testQuerySeparatorDoesNotDependOnIni(): void
    {
        $previous = ini_set('arg_separator.output', ';');

        try {
            $response = new CurlTransport()->post(self::$baseUrl . '/echo', ['text' => 'a', 'n' => '1'], false, 5);
            self::assertSame(['text' => 'a', 'n' => '1'], self::decode($response->body)['post']);
        } finally {
            if (is_string($previous)) {
                ini_set('arg_separator.output', $previous);
            }
        }
    }

    public function testCapsInMemoryResponses(): void
    {
        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('byte limit');

        new CurlTransport(maxResponseBytes: 64)->get(self::$baseUrl . '/file', 5);
    }

    public function testRejectsUrlsWithCredentials(): void
    {
        $this->expectException(NetworkException::class);

        new CurlTransport()->get('http://user:secret@127.0.0.1/', 1);
    }

    public function testRejectsNonPositiveLimits(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CurlTransport(connectTimeout: 0);
    }

    public function testDownloadRejectsADestinationThatCannotBeOpened(): void
    {
        $directory = sys_get_temp_dir() . '/tgbotphp-dir-' . bin2hex(random_bytes(2));
        mkdir($directory);

        try {
            new CurlTransport()->download(self::$baseUrl . '/file', $directory, 5);
            self::fail('Opened a directory for writing');
        } catch (NetworkException $e) {
            self::assertStringContainsString('Unable to open', $e->getMessage());
        } finally {
            rmdir($directory);
        }
    }

    public function testDownloadReportsAFailedTransfer(): void
    {
        $destination = tempnam(sys_get_temp_dir(), 'tgbotphp');
        self::assertIsString($destination);

        try {
            new CurlTransport()->download('http://127.0.0.1:1/', $destination, 2);
            self::fail('Downloaded from a closed port');
        } catch (NetworkException $e) {
            self::assertStringContainsString('cURL error', $e->getMessage());
        } finally {
            @unlink($destination);
        }
    }

    public function testReportsAFailedCurlInit(): void
    {
        RuntimeControls::$failCurlInit = true;

        try {
            new CurlTransport()->get(self::$baseUrl . '/echo', 5);
            self::fail('Used a transport whose curl_init() failed');
        } catch (NetworkException $e) {
            self::assertSame('Failed to initialize cURL', $e->getMessage());
        } finally {
            RuntimeControls::reset();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $body): array
    {
        return Value::map(json_decode($body, true, flags: JSON_THROW_ON_ERROR));
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $name = Value::string(stream_socket_get_name($socket, false));
        fclose($socket);

        return Value::int(parse_url('tcp://' . $name, PHP_URL_PORT));
    }
}
