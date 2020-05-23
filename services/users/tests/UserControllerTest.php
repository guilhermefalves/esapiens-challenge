<?php

namespace Tests;

use App\Models\User;
use Illuminate\Support\Arr;

class UserControllerTest extends TestCase
{
    use MigrateAfterTestsTrait;

    /**
     * @return array
     */
    public function testCreateSuccess(): array
    {
        $user = factory(User::class)->make()->makeVisible('password')->toArray();
        $this->post('/user', $user)->seeJsonStructure([
            'message', 'status' , 'id'
        ]);
        $this->response->assertStatus(201);
        $this->seeInDatabase('users', Arr::except($user, 'password'));

        $user['id'] = $this->response->getData()->id;
        return $user;
    }

    /**
     * @depends testCreateSuccess
     * @return void
    */
    public function testGet(array $user)
    {
        $id = $user['id'];
        $this->get("/user/$id")->seeJsonStructure([
            'message', 'status'
        ]);
        
        $this->response->assertStatus(200);

        $this->assertEquals($this->response->getData()->data->id, $id);
    }

    /**
     * @depends testCreateSuccess
     * @return void
     */
    public function testLogin(array $user)
    {
        $login = Arr::only($user, ['email', 'password']);
        $this->post('/login', $login)->seeJsonStructure([
            'status', 'message', 'jwt'
        ]);

        $this->response->assertStatus(200);
    }

    /**
     * @depends testCreateSuccess
     * @return void
     */
    public function testUpdate(array $user)
    {
        $id = $user['id'];
        $user = factory(User::class)->make()->makeVisible('password')->toArray();
        $this->put("/user/$id", $user)->seeJsonStructure([
            'message', 'status'
        ]);

        $this->response->assertStatus(200);
        $this->seeInDatabase('users', Arr::except($user, 'password'));
    }

     /**
      * @depends testCreateSuccess
      * @return void
      */
    public function testDelete(array $user)
    {
        $id = $user['id'];
        $this->delete("user/$id")->seeJsonStructure([
            'status', 'message'
        ]);

        $this->response->assertStatus(200);

        $user = User::find($id);
        $found  = (boolean) $user;
        $this->assertFalse($found);

        // Verifico a propriedade deleted_at
        $user = User::onlyTrashed()->find($id);
        $found  = (boolean) $user;
        $this->assertTrue($found);
    }
}