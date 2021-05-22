<?php

declare(strict_types = 1);

namespace SimplePhpModelSystem\Test\Models;

use examples\User;
use SimplePhpModelSystem\Tests\DatabaseAbstractTestCase;

class ExpositionTest extends DatabaseAbstractTestCase
{

    private function cleanupUsers(): void
    {
        $rowsNbr = User::count();
        if ($rowsNbr !== 0) {
            User::deleteAll();
        }
    }

    public function testInsertSuccess(): void
    {
        $this->cleanupUsers();
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

        $this->assertSame(
            [
                'user_uuid' => '5c8169b1-d6ef-4415-8c39-e1664df8b954',
                'first_name' => 'Gwénola',
                'last_name' => 'Etheve',
                'date_of_birth' => null,
            ],
            $user->toArray()
        );

        $this->assertSame('5c8169b1-d6ef-4415-8c39-e1664df8b954', $user->getValue('user_uuid'));
        $this->assertTrue($user1->delete());
    }

    public function testRefresh(): void
    {
        $this->cleanupUsers();
        $this->assertSame(0, User::count());
        $user1 = User::create(
            '5c8169b1-d6ef-4415-8c39-e1664df8b954',
            'Gwénola',
            'Etheve',
            null
        );
        $this->assertNull(
            User::findById(
                '5c8169b1-d6ef-4415-8c39-e1664df8b954'
            )
        );
        $this->assertTrue($user1->save());

        $user1bis = User::findById(
            '5c8169b1-d6ef-4415-8c39-e1664df8b954'
        );

        $this->assertNotNull($user1bis);
        /** @var User $user1bis */

        $this->assertEquals($user1, $user1bis, 'The data should be the same');
        $user1->setLastName('ETHEVE');
        $this->assertNotEquals($user1, $user1bis, 'Can not be the same data, user1bis is not up to date with the DB');
        $user1bis->refresh();
        $this->assertNotEquals($user1, $user1bis, 'Still not, user1 has not sent the changes');
        $user1->update();
        $this->assertNotEquals($user1, $user1bis, 'Changes where sent, but user1bis needs a refresh');
        $user1bis->refresh();
        $this->assertEquals($user1, $user1bis, 'user1bis was refreshed, objects should be the same');

        $this->assertSame(1, User::count());
        $users = User::fetchAll();
        $this->assertEquals(
            [// Not same because it is not the same object but only the same contents
                $user1
            ],
            $users
        );
    }

    public function testFetchAll(): void
    {
        $this->cleanupUsers();
        $this->assertSame(0, User::count());
        $user1 = User::create(
            '5c8169b1-d6ef-4415-8c39-e1664df8b954',
            'Gwénola',
            'Etheve',
            null
        );

        $this->assertTrue($user1->save());
        $this->assertSame(1, User::count());
        $users = User::fetchAll();
        $this->assertEquals(
            [// Not same because it is not the same object but only the same contents
            $user1
            ],
            $users
        );
    }

    public function testFetchAllOrder(): void
    {
        $this->cleanupUsers();
        $this->assertSame(0, User::count());
        $user1 = User::create(
            '5c8169b1-d6ef-4415-8c39-e1664df8b954',
            'Gwénola',
            'Etheve',
            null
        );
        $this->assertTrue($user1->save());
        $user2 = User::create(
            '874d1aa5-4db3-4953-88dd-2dd58a298d3e',
            'William',
            'Desportes',
            null
        );

        $this->assertTrue($user2->save());
        $this->assertSame(2, User::count());
        $users = User::fetchAll(
            [
                'first_name' => 'ASC',
            ]
        );
        $this->assertEquals(
            [// Not same because it is not the same object but only the same contents
                0 => $user1,
                1 => $user2
            ],
            $users
        );
        $this->assertNotEquals(
            [// Not same because it is not the same object but only the same contents
                0 => $user2,
                1 => $user1
            ],
            $users
        );
        $this->assertSame(2, User::count());
        $users = User::fetchAll(
            [
                'first_name' => 'DESC',
            ]
        );
        $this->assertEquals(
            [// Not same because it is not the same object but only the same contents
                0 => $user2,
                1 => $user1
            ],
            $users
        );
        $this->assertNotEquals(
            [// Not same because it is not the same object but only the same contents
                0 => $user1,
                1 => $user2
            ],
            $users
        );
    }

}
