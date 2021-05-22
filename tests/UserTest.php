<?php

declare(strict_types = 1);

namespace SimplePhpModelSystem\Test\Models;

use examples\User;
use SimplePhpModelSystem\Tests\DatabaseAbstractTestCase;

class ExpositionTest extends DatabaseAbstractTestCase
{

    public function testInsertSuccess(): void
    {
        $rowsNbr = User::count();
        if ($rowsNbr !== 0) {
            User::deleteAll();
        }
        $this->assertSame(0, User::count());
        $user1 = User::create(
            '5c8169b1-d6ef-4415-8c39-e1664df8b954',
            'Gwénola',
            'Etheve',
            null
        );

        $this->assertTrue($user1->save());
        $this->assertWasQuery(
            'INSERT INTO `users` (user_uuid, first_name, last_name, date_of_birth) VALUES (?, ?, ?, ?);',
            [
                '5c8169b1-d6ef-4415-8c39-e1664df8b954',
                'Gwénola',
                'Etheve',
                null,
            ]
        );
        $this->assertSame(1, User::count());
        $user = User::findByUuid('5c8169b1-d6ef-4415-8c39-e1664df8b954');
        $this->assertNotNull($user);
        /** @var User $user */
        $this->assertSame(
            [
                '5c8169b1-d6ef-4415-8c39-e1664df8b954',
                'Gwénola',
                'Etheve',
                null,
            ],
            $user->getValues()
        );

        $this->assertSame(
            [
                'user_uuid',
                'first_name',
                'last_name',
                'date_of_birth',
            ],
            $user->getKeys()
        );

        $this->assertSame('5c8169b1-d6ef-4415-8c39-e1664df8b954', $user->getValue('user_uuid'));
    }

}
