<?php

declare(strict_types = 1);

namespace examples;

use DateTime;
use SimplePhpModelSystem\AbstractModel;

class User extends AbstractModel
{
    /**
     * @var string
     */
    protected $table = 'users';

    /**
     * {@inheritDoc}
     */
    protected $primaryKey = 'user_uuid';

    public static function create(
        string $userUuid,
        string $firstName,
        ?string $lastName,
        ?DateTime $dob
    ): self {
        $instance = new static();
        $instance->setData(
            [
                'user_uuid' => $userUuid,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'date_of_birth' => $dob === null ? null : $dob->format('Y-m-d'),
            ]
        );

        return $instance;
    }

    public static function findByUuid(
        string $userUuid
    ): ?self {
        return parent::findWhere(
            [
                'user_uuid' => $userUuid,
            ]
        );
    }

}
