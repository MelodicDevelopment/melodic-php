<?php

declare(strict_types=1);

namespace Tests\Http\Exception;

use Melodic\Http\Exception\BadRequestException;
use Melodic\Http\Exception\HttpException;
use Melodic\Http\Exception\MethodNotAllowedException;
use Melodic\Http\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

final class HttpExceptionTest extends TestCase
{
    public function testHttpExceptionStatusCodeAndMessage(): void
    {
        $exception = new HttpException(418, 'I am a teapot');

        $this->assertSame(418, $exception->getStatusCode());
        $this->assertSame('I am a teapot', $exception->getMessage());
    }

    public function testHttpExceptionWithPreviousException(): void
    {
        $previous = new \RuntimeException('root cause');
        $exception = new HttpException(500, 'Server error', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testHttpExceptionExtendsRuntimeException(): void
    {
        $exception = new HttpException(500, 'error');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testHttpExceptionStaticNotFound(): void
    {
        $exception = HttpException::notFound();

        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('Not Found', $exception->getMessage());
    }

    public function testHttpExceptionStaticNotFoundCustomMessage(): void
    {
        $exception = HttpException::notFound('Page missing');

        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('Page missing', $exception->getMessage());
    }

    public function testHttpExceptionStaticForbidden(): void
    {
        $exception = HttpException::forbidden();

        $this->assertSame(403, $exception->getStatusCode());
        $this->assertSame('Forbidden', $exception->getMessage());
    }

    public function testHttpExceptionStaticBadRequest(): void
    {
        $exception = HttpException::badRequest();

        $this->assertSame(400, $exception->getStatusCode());
        $this->assertSame('Bad Request', $exception->getMessage());
    }

    public function testHttpExceptionStaticMethodNotAllowed(): void
    {
        $exception = HttpException::methodNotAllowed();

        $this->assertSame(405, $exception->getStatusCode());
        $this->assertSame('Method Not Allowed', $exception->getMessage());
    }

    // BadRequestException tests

    public function testBadRequestExceptionDefaultMessage(): void
    {
        $exception = new BadRequestException();

        $this->assertSame(400, $exception->getStatusCode());
        $this->assertSame('Bad Request', $exception->getMessage());
    }

    public function testBadRequestExceptionCustomMessage(): void
    {
        $exception = new BadRequestException('Invalid input');

        $this->assertSame(400, $exception->getStatusCode());
        $this->assertSame('Invalid input', $exception->getMessage());
    }

    public function testBadRequestExceptionWithPrevious(): void
    {
        $previous = new \InvalidArgumentException('bad value');
        $exception = new BadRequestException('Validation failed', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testBadRequestExceptionExtendsHttpException(): void
    {
        $exception = new BadRequestException();

        $this->assertInstanceOf(HttpException::class, $exception);
    }

    // NotFoundException tests

    public function testNotFoundExceptionDefaultMessage(): void
    {
        $exception = new NotFoundException();

        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('Not Found', $exception->getMessage());
    }

    public function testNotFoundExceptionCustomMessage(): void
    {
        $exception = new NotFoundException('User not found');

        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('User not found', $exception->getMessage());
    }

    public function testNotFoundExceptionWithPrevious(): void
    {
        $previous = new \RuntimeException('lookup failed');
        $exception = new NotFoundException('Not Found', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testNotFoundExceptionExtendsHttpException(): void
    {
        $exception = new NotFoundException();

        $this->assertInstanceOf(HttpException::class, $exception);
    }

    // MethodNotAllowedException tests

    public function testMethodNotAllowedExceptionDefaultMessage(): void
    {
        $exception = new MethodNotAllowedException();

        $this->assertSame(405, $exception->getStatusCode());
        $this->assertSame('Method Not Allowed', $exception->getMessage());
    }

    public function testMethodNotAllowedExceptionCustomMessage(): void
    {
        $exception = new MethodNotAllowedException('POST not supported');

        $this->assertSame(405, $exception->getStatusCode());
        $this->assertSame('POST not supported', $exception->getMessage());
    }

    public function testMethodNotAllowedExceptionWithPrevious(): void
    {
        $previous = new \LogicException('route mismatch');
        $exception = new MethodNotAllowedException('Method Not Allowed', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testMethodNotAllowedExceptionExtendsHttpException(): void
    {
        $exception = new MethodNotAllowedException();

        $this->assertInstanceOf(HttpException::class, $exception);
    }
}
