<?php

namespace Unleash\Client\Bundle\Test\DependencyInjection\Dsn;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionException;
use ReflectionObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\EnvVarProcessor;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Unleash\Client\Bundle\DependencyInjection\Dsn\LateBoundDsnParameter;
use Unleash\Client\Bundle\Test\TestKernel;

final class LateBoundDsnParameterTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        while (true) {
            $previousHandler = set_exception_handler(static function () {
                return null;
            });
            restore_exception_handler();

            if ($previousHandler === null) {
                break;
            }
            restore_exception_handler();
        }
    }

    /**
     * @dataProvider preprocessorsData
     *
     * @param string $envValue The raw value of the string that will be assigned to the raw env name
     * @param string $envName  The name of the environment variable including all preprocessors
     * @param mixed  $expected If it is a callable, it's called with the result as a parameter to do complex assertions
     *                         otherwise a strict comparison with the result is done.
     *
     * @throws ReflectionException
     */
    public function testPreprocessors(string $envValue, string $envName, $expected)
    {
        $preprocessors = [new EnvVarProcessor(self::getContainer(), null), $this->customEnvProcessor()];

        $rawEnv = array_reverse(explode(':', $envName))[0];
        $_ENV[$rawEnv] = $envValue;

        $instance = new LateBoundDsnParameter($envName, '', $preprocessors);
        $getEnvValue = (new ReflectionObject($instance))->getMethod('getEnvValue');
        $getEnvValue->setAccessible(true);
        $result = $getEnvValue->invoke($instance, $envName);

        if (is_callable($expected)) {
            $expected($result);
        } else {
            self::assertSame($expected, $result);
        }
    }

    public static function preprocessorsData(): iterable
    {
        yield ['test', 'TEST_ENV', 'test'];
        yield ['1', 'string:int:TEST_ENV', '1'];
        yield ['1', 'bool:TEST_ENV', true];
        yield ['1', 'not:bool:TEST_ENV', false];
        yield ['55', 'int:TEST_ENV', 55];
        yield ['55.5', 'float:TEST_ENV', 55.5];
        yield ['JSON_THROW_ON_ERROR', 'const:TEST_ENV', JSON_THROW_ON_ERROR];
        yield [base64_encode('test'), 'base64:TEST_ENV', 'test'];
        yield [json_encode(['a' => 1, 'b' => 'c']), 'json:TEST_ENV', ['a' => 1, 'b' => 'c']];
        yield ['test_%some_param%', 'resolve:TEST_ENV', 'test_test'];
        yield ['a,b,c,d', 'csv:TEST_ENV', ['a', 'b', 'c', 'd']];
        if (PHP_VERSION_ID > 80100) {
            yield ['a,b,c,d', 'shuffle:csv:TEST_ENV', function (array $result) {
                self::assertTrue(in_array('a', $result));
                self::assertTrue(in_array('b', $result));
                self::assertTrue(in_array('c', $result));
                self::assertTrue(in_array('d', $result));
            }];
        }
        yield [__DIR__ . '/../../data/file.txt', 'file:TEST_ENV', "hello\n"];
        yield [__DIR__ . '/../../data/file.php', 'require:TEST_ENV', 'test'];
        yield [__DIR__ . '/../../data/file.txt', 'trim:file:TEST_ENV', 'hello'];
        yield [json_encode(['a' => 'test']), 'key:a:json:TEST_ENV', 'test'];
        yield ['https://testUser:testPwd@test-domain.org:8000/test-path?testQuery=testValue#testFragment', 'url:TEST_ENV', function (array $result) {
            self::assertSame('https', $result['scheme']);
            self::assertSame('test-domain.org', $result['host']);
            self::assertSame('testUser', $result['user']);
            self::assertSame('testPwd', $result['pass']);
            self::assertSame('test-path', $result['path']);
            self::assertSame('testQuery=testValue', $result['query']);
            self::assertSame('testFragment', $result['fragment']);
            self::assertSame(8000, $result['port']);
        }];
        yield ['https://testUser:testPwd@test-domain.org:8000/test-path?testQuery=testValue#testFragment', 'key:testQuery:query_string:key:query:url:TEST_ENV', 'testValue'];
        yield ['whatever', 'defined:TEST_ENV', true];
        yield ['whatever', 'test:TEST_ENV', 'test'];
    }

    public function testDefault()
    {
        $envName = 'default:some_param:NONEXISTENT_ENV'; // some_param is from kernel container
        $preprocessors = [new EnvVarProcessor(self::getContainer(), null)];
        $instance = new LateBoundDsnParameter($envName, '', $preprocessors);
        $getEnvValue = (new ReflectionObject($instance))->getMethod('getEnvValue');
        $result = $getEnvValue->invoke($instance, $envName);
        self::assertSame('test', $result);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    private function customEnvProcessor(): EnvVarProcessorInterface
    {
        return new class implements EnvVarProcessorInterface {
            public function getEnv(string $prefix, string $name, Closure $getEnv): string
            {
                return 'test';
            }

            public static function getProvidedTypes(): array
            {
                return ['test' => 'string'];
            }
        };
    }
}
