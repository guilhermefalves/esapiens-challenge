<?php

namespace Tests;

use App\Models\Notification;
use App\Models\Notifications;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Firebase\JWT\JWT;

class NotificationControllerTest extends TestCase
{
    use MigrateAfterTestsTrait;

    /**
     * Headers que serão enviados nos requests de criação
     * @param array $headersSender
     */
    private array $headersSender = [];
    private int $senderID = 1;

    /**
     * Headers que serão enviados nos requests de visualizacão (get)
     * @param array $headersReceiver
     */
    private array $headersReceiver = [];
    private int $receiverID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $payload = [
            'iat' => Carbon::now()->format('U'),
            'exp' => Carbon::now()->addMinutes(2)->format('U'),
            'user' => [
                'id' => $this->senderID
            ]
        ];
        $jwt = JWT::encode($payload, config('jwt.key'));
        $this->headersSender['Authorization'] = 'Bearer ' . $jwt;

        $payload['user']['id'] = $this->receiverID;
        $jwt = JWT::encode($payload, config('jwt.key'));
        $this->headersReceiver['Authorization'] = 'Bearer ' . $jwt;
    }

    public function testCreate()
    {
        $notification = factory(Notification::class)
            ->make(['to' => 2])
            ->makeVisible(['content'])
            ->makeHidden(['from', 'sended', 'sended_at', 'readed', 'readed_at'])
            ->toArray();

        $this->post('/notification', $notification, $this->headersSender)->seeJsonStructure([
            'status', 'message', 'id'
        ]);
        $this->response->assertStatus(201);
        $this->seeInDatabase('notifications', $notification);

        return Notification::find($this->response->getData()->id);
    }

    /**
     * @depends testCreate
     */
    public function testIndex(Notification $notification)
    {
        $ntfID = $notification->id;
        $this->get('/notification', $this->headersReceiver)->seeJsonStructure([
            'status', 'message', 'data', 'pagination'
        ]);

        $this->response->assertStatus(200);

        $notifications = $this->response->getData()->data;
        $notificationInIndex = array_filter($notifications, function($ntf) use ($ntfID) {
            return $ntf->id == $ntfID;
        })[0];

        $this->assertNotEmpty($notificationInIndex);
        $this->assertEquals((array)$notificationInIndex, $notification->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testIndexNotReceiver(Notification $notification)
    {
        $ntfID = $notification->id;
        $this->get('/notification', $this->headersSender)->seeJsonStructure([
            'status', 'message', 'data', 'pagination'
        ]);

        $this->response->assertStatus(200);

        $notifications = $this->response->getData()->data;
        $notificationInIndex = array_filter($notifications, function($ntf) use ($ntfID) {
            return $ntf->id == $ntfID;
        });

        $this->assertEmpty($notificationInIndex);
    }

    /**
     * @depends testCreate
     */
    public function testIndexAfterExpire(Notification $notification)
    {
        $ntfID = $notification->id;
        $createdAtBeforeUpdate = $notification->created_at;
        Notification::where('id', $notification->id)
            ->update(['created_at' => '2010-05-25 20:30:00']);

        $this->get('/notification', $this->headersReceiver)->seeJsonStructure([
            'status', 'message', 'data', 'pagination'
        ]);

        $this->response->assertStatus(200);
        $notifications = $this->response->getData()->data;
        $notificationInIndex = array_filter($notifications, function($ntf) use ($ntfID){
            return $ntf->id == $ntfID;
        });

        $this->assertEmpty($notificationInIndex);

        Notification::where('id', $notification->id)
            ->update(['created_at' => $createdAtBeforeUpdate]);
    }

    /**
     * @depends testCreate
     */
    public function testIndexAll(Notification $notification)
    {
        $ntfID = $notification->id;
        $this->get('/notification', $this->headersReceiver)->seeJsonStructure([
            'status', 'message', 'data', 'pagination'
        ]);

        $this->response->assertStatus(200);
        $notifications = $this->response->getData()->data;
        $notificationInIndex = array_filter($notifications, function($ntf) use ($ntfID){
            return $ntf->id == $ntfID;
        });

        $this->assertNotEmpty($notificationInIndex);
    }

    /**
     * @depends testCreate
     */
    public function testIndexNew(Notification $notification)
    {
        $this->get('/notification/new', $this->headersReceiver)->seeJsonStructure([
            'status', 'message', 'data', 'hasMore', 'total', 'perPage'
        ]);

        $this->response->assertStatus(200);
        $this->assertNotEmpty($this->response->getData()->data);

        $response = $this->response->getData();
        $this->assertIsBool($response->hasMore);
        $this->assertIsInt($response->total);
        $this->assertIsInt($response->perPage);

        $ntfInDB = Notification::where('id', $notification->id)
            ->get(['sended', 'sended_at'])
            ->toArray();
        $this->assertNotEmpty($ntfInDB, 'notification not in db after read');

        $ntfInDB = $ntfInDB[0];
        $this->assertTrue((bool) $ntfInDB['sended']);
        $this->assertNotNull($ntfInDB['sended_at']);
    }

    /**
     * @depends testIndexNew
     */
    public function testIndexNewAfterRead()
    {
        $this->get('/notification/new', $this->headersReceiver)->seeJsonStructure([
            'status', 'message', 'data', 'hasMore', 'total', 'perPage'
        ]);

        $this->response->assertStatus(200);
        $this->assertEmpty($this->response->getData()->data);

        $countNtfsInDB = Notification::where('to', $this->receiverID)->count();
        $this->assertNotEquals(0, $countNtfsInDB);
    }
}
