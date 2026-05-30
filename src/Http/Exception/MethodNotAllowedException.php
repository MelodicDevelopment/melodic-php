<?php

declare(strict_types=1);

namespace Melodic\Http\Exception;

class MethodNotAllowedException extends HttpException
{
    /**
     * @param string[] $allowedMethods Methods permitted for the requested path,
     *                                 surfaced to the client via the Allow header.
     */
    public function __construct(
        string $message = 'Method Not Allowed',
        ?\Throwable $previous = null,
        private readonly array $allowedMethods = [],
    ) {
        parent::__construct(405, $message, $previous);
    }

    /**
     * Build a 405 advertising the methods a path does support.
     *
     * @param string[] $allowedMethods
     */
    public static function forMethods(array $allowedMethods): self
    {
        return new self(allowedMethods: $allowedMethods);
    }

    /** @return string[] */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
