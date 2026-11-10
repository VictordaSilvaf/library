<?php

declare(strict_types=1);

namespace Src\Catalog\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Image
{
    public function __construct(
        public string $url,
    ) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(
                'Invalid image URL.'
            );
        }

        if (!in_array(
            strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''),
            ['http', 'https'],
            true
        )) {
            throw new InvalidArgumentException(
                'Image URL must use HTTP or HTTPS.'
            );
        }
    }
}
