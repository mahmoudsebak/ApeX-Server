<?php

namespace Tests\Unit;

use Tests\TestCase;
use PHPUnit\Framework\Assert;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use App\Models\Message;
use App\Models\User;

class InboxTest extends TestCase
{
    /**
     * Test a valid request to inbox.
     * Assumes that there are some records in the database
     *
     * @test
     *
     * @return void
     */
    public function validInbox()
    {
        //get a message sender
        $user = Message::inRandomOrder()->firstOrFail()->sender()->first();
        $loginResponse = $this->json(
            'POST',
            '/api/SignIn',
            ['username' => $user->username, 'password' => 'monda21']
        )->assertStatus(200);
        $token = $loginResponse->json('token');
        $userID = $user->id;

        $response = $this->json('POST', 'api/InboxMessages', compact('token'));
        $structure = ["sent" , "received" => ["read", "unread", "all"]];
        $response->assertStatus(200)->assertJsonStructure($structure);

        $sent = $response->json('sent');
        foreach ($sent as $mes) {
            $this->assertDatabaseHas(
                'messages',
                [
                    'id' => $mes['id'], 'parent' => null,
                    'sender' => $userID, 'delSend' => false
                ]
            );
        }

        $read = $response->json('received')['read'];
        foreach ($read as $mes) {
            $this->assertDatabaseHas(
                'messages',
                [
                    'id' => $mes['id'], 'parent' => null,
                    'receiver' => $userID, 'delReceive' => false, 'received' => true
                ]
            );
        }

        $unread = $response->json('received')['unread'];
        foreach ($unread as $mes) {
            $this->assertDatabaseHas(
                'messages',
                [
                    'id' => $mes['id'], 'parent' => null,
                    'receiver' => $userID, 'delReceive' => false, 'received' => false
                ]
            );
        }

        $all = $response->json('received')['all'];

        $this->assertTrue(count($all) == count(array_merge($read, $unread)));
    }

    /**
     * Test invalid max
     * Assumes that there are some records in the database
     *
     * @test
     *
     * @return void
     */
    public function invalidMax()
    {
        $user = User::firstOrFail();
        $loginResponse = $this->json(
            'POST',
            '/api/SignIn',
            ['username' => $user->username, 'password' => 'monda21']
        )->assertStatus(200);
        $token = $loginResponse->json('token');
        $max = "bla";
        $response = $this->json(
            'POST',
            'api/InboxMessages',
            compact('token', 'max')
        );
        $response->assertStatus(400)->assertSee('max');
    }

    /**
     * Test invalid token
     * Assumes that there are some records in the database
     *
     * @test
     *
     * @return void
     */
    public function invalidtoken()
    {
        $token = '-1';
        $response = $this->json(
            'POST',
            'api/InboxMessages',
            compact('token')
        );
        $response->assertStatus(400)->assertSee('Not authorized');
    }
}
