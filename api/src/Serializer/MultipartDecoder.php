<?php

namespace App\Serializer;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

final readonly class MultipartDecoder implements DecoderInterface
{
    public const FORMAT = 'multipart';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function decode(string $data, string $format, array $context = []): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $data = [];

        foreach ($request->request->all() as $key => $value) {
            if (!is_string($value)) {
                $data[$key] = $value;

                continue;
            }

            $decoded = json_decode($value, true);
            $data[$key] = JSON_ERROR_NONE === json_last_error() ? $decoded : $value;
        }

        return $data + $request->files->all();
    }

    public function supportsDecoding(string $format): bool
    {
        return self::FORMAT === $format;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            'object' => null,
            '*' => false,
        ];
    }
}
