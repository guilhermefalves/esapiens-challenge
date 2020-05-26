<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Model;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\{ Arr, Carbon};
use Illuminate\Support\Facades\Queue;
use LumenBaseCRUD\Controller as BaseCRUD;

/**
 * Controller das notificações
 * 
 * @author Guilherme Alves <guihalves20@gmail.com>
 */
class NotificationController extends BaseCRUD
{
    /**
     * Armazena o usuário que fez login (payload do JWT)
     */
    private Object $user;

    protected $model = Notification::class;

    protected array $postRules = [
        'to'         => 'integer|required',
        'mail_to'    => 'email|required',
        'content'    => 'string|required',
        'identifier' => 'integer'
    ];

    public function __construct(Request $request)
    {
        $jwt = str_replace('Bearer ', '', $request->header('Authorization'));
        $payload = JWT::decode($jwt, config('jwt.key'), config('jwt.alg'));
        $this->user = $payload->user;
    }

    /**
     * Executada antes a criação de uma notificação
     * Responsável por adicionar dados adicionais a notificação
     *
     * @param Model $notification
     * @return void
     */
    protected function preStore(array &$data)
    {
        // Adiciono os dados do usuário que gerou a notificação
        $data['from'] = $this->user->id;
    }

    /**
     * Executada após a criação de uma notificação
     * Responsável por colocar o envio do e-mail em uma fila com um delay. O 
     * delay dá tempo para que a notificação possa ser cancelada em caso de erro
     *
     * @param Model $notification
     * @return void
     */
    protected function posStore(Model $notification)
    {
        $delayToSend = config('app.notificationMailDelay');
        Queue::later($delayToSend, new SendEmail($notification));
    }

    /**
     * Executada antes de mostrar uma notificação
     * Responsável por verificar se o usuário tem permissão para visualiza-la
     *
     * @param Model $notification
     * @return void
     */
    protected function preShow(Model &$notification)
    {
        // Se a notificação não for do usuário e não tiver sido enviada pelo usuário
        $userID = $this->user->id;
        if ($notification->to != $userID && $notification->from != $userID) {
            return $this->response(403, [], 'Você não tem permissão para visualizar essa notificação');
        }
    }

    /**
     * Retorna todas as notfificações de um user ID
     *
     * @return JsonResponse
     */
    public function indexAll(): JsonResponse
    {
        $pageSize = config('database.pageSize');
        $result   = Notification::where('to', $this->user->id)->paginate($pageSize);
        $result   = $result->toArray();

        // Recupero os dados e a paginação
        $data       = $result['data'];
        $pagination = ($data) ? Arr::except($result, 'data') : null;

        return $this->response(200, compact(['data', 'pagination']));
    }

    /**
     * Retorna as novas notfificações de um user ID
     *
     * @return JsonResponse
     */
    public function indexNew(): JsonResponse
    {
        $perPage = (int) config('database.pageSize');
        $result   = Notification::where('to', $this->user->id)
            ->where('readed', false)
            ->take($perPage);

        // Verifico se existem mais elementos
        $total   = $result->count();
        $hasMore = $total > $perPage;

        // Recupero os dados e a paginação
        $data = $result->get();

        // E por fim, marco essas notificações como lidas
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $result->update(['readed' => true, 'readed_at' => $now]);

        return $this->response(200, compact(['data', 'hasMore', 'total', 'perPage']));
    }

    /**
     * Retorna as novas notfificações de um user ID
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) config('database.pageSize');
        $maxDate = Carbon::now()->sub(config('app.notificationTTL'));

        // Busco as notificações que ainda não expiraram
        $result  = Notification::where('to', $this->user->id)
            ->whereDate('created_at', '>=', $maxDate)
            ->paginate($perPage)
            ->toArray();

        // Recupero os dados e a paginação
        $data       = $result['data'];
        $pagination = Arr::except($result, 'data');
        return $this->response(200, compact(['data', 'pagination']));
    }
}
