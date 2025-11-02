<?php

namespace App\Smtp\Doctrine\Type;

use App\Smtp\Service\DkimChecker\DkimResult;
use App\Smtp\Service\DkimChecker\DkimResultStatus;
use App\Smtp\Service\SpfChecker\SpfResult;
use App\Smtp\Service\SpfChecker\SpfResultStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class DkimResultType extends Type
{
    public const NAME = 'dkim_result';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'JSON';
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (\is_null($value)) {
            return null;
        }

        if (false === ($value instanceof DkimResult)) {
            throw new \InvalidArgumentException('Value must be instance of DkimResult or null');
        }

        return \json_encode([
            'status'  => $value->status->value,
            'message' => $value->message,
            'domain'  => $value->domain,
        ], JSON_THROW_ON_ERROR);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?DkimResult
    {
        if ($value === null) {
            return null;
        }

        $data = \json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return new DkimResult(
            DkimResultStatus::from($data['status']),
            $data['message'] ?? null,
            $data['domain'] ?? null
        );
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
