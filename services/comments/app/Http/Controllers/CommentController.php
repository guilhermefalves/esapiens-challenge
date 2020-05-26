<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;
use LumenBaseCRUD\Controller as BaseCRUD;
use Firebase\JWT\JWT;
use App\Libraries\{NotificationService, TransactionService, UserService};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

/**
 * Controller dos comentários
 * 
 * @author Guilherme Alves <guihalves20@gmail.com>
 */
class CommentController extends BaseCRUD
{
    protected $model = Comment::class;

    /**
     * Armazena o usuário que fez login (payload do JWT)
     */
    private Object $user;

    public function __construct(Request $request)
    {
        $jwt = str_replace('Bearer ', '', $request->header('Authorization'));
        $payload = JWT::decode($jwt, config('jwt.key'), config('jwt.alg'));
        $this->user = $payload->user;
    }

    protected array $postRules = [
        'post_id' => 'exists:posts,id',
        'coins'   => 'integer',
        'title'   => 'string|max:100|required',
        'content' => 'string|required'
    ];

    /**
     * Executada antes da criação de um comentário 
     *
     * @param array $data
     * @return void
     */
    protected function preStore(array &$data)
    {
        // Adiciono o id do usuário logado
        $data['user_id'] = $this->user->id;

        // Qauntas moedas estão sendo utilizadas no destaque
        $coins = (isset($data['coins'])) ? $data['coins'] : 0;

        // Verifico se o usuário fez muitos comentários no intervalo de tempo
        if ($this->madeTooManyComments()) {
            return $this->response(403, [], 'Você fez muitos comentários nos ultimos minutos');
        }

        // Começo verificando se o usuário pode comentar
        if (!$this->canComment($data['post_id'], (bool) $coins)) {
            return $this->response(403, [], 'Você não pode comentar nesse post');
        }

        // Verifico se o usuário tem saldo para comentar
        if (!$this->canHighlight($coins)) {
            return $this->response(403, [], 'Você não tem saldo suficiente');
        }

        // Adiciona as coins usadas e até quando será o destaque
        $now   = Carbon::now();
        $data['coins']        = $coins;
        $data['created_at']   = $now->format('Y-m-d H:i:s');
        $data['highlight_up'] = $coins ? $now->addMinutes($coins)->format('Y-m-d H:i:s') : null;
    }

    /**
     * Executada após a criação de um comentário
     * É responsável por criar a transação (se houver destaque) e a notificação
     *
     * @param Model $comment
     * @return void
     */
    protected function posStore(Model $comment)
    {
        $usingCoins = (bool) $comment->coins;

        // Se estiver dando destaque a notificação, vou criar sua transaction
        if ($usingCoins) {
            // Recupero a URL e o Secret
            $url    = config('services.transaction.host');
            $secret = config('services.transaction.secret');

            // E crio a transaction
            $transactionService = new TransactionService($url, $secret, $this->user);
            $transactionID = $transactionService->create($comment->id, $comment->coins);
        }

        if ($usingCoins && !$transactionID) {
            $comment->forceDelete();
            return $this->response(500, [], 'Problemas ao dar destaque nesse comentário');
        }

        $notificationID = $this->createNotification($comment);
        if (!$notificationID) {
            $comment->forceDelete();
            return $this->response(500, [], 'Problemas ao criar notificações para o comentário');
        }

        // Comment, Transaction e Notification criados, vou confirmar a Transaction
        if ($usingCoins) {
            $transactionConfirmed = $transactionService->confirm($transactionID);
        }

        // Se estiver dando destaque ao comentário e a trancaction não for confirmada
        if ($usingCoins && !$transactionConfirmed) {
            // preciso deletar o Comment e a Notification
            $comment->forceDelete();
            $this->deleteNotification($notificationID);
            return $this->response(500, [], 'Problemas ao confirmar o destaque para o comentário');
        }

        // Se tudo ocorreu bem, vou salvar uma key no redis para contar
        // quantos comentários o usuário fez no intervalo de tempo
        $key = sprintf('comment-%d-%d-%d', $this->user->id, $comment->post_id, $comment->id);
        $keyTTL = config('app.commentsTime');
        Redis::set($key, $comment->created_at);
        Redis::expire($key, $keyTTL);
    }

    /**
     * Executada antes da deleção de um comentário
     * Responsável por verificar se o usuário pode realiza-la
     *
     * @param Model $object
     * @return void|JsonResponse
     */
    protected function preDelete(Model &$comment)
    {
        // Se o usuário for dono do comentário, pode deleta-lo
        if ($this->user->id == $comment->user_id ) {
            return;
        }

        // Se o usuario for o dono do post, pode deletar o comentário
        if ($this->user->id == $comment->post->user_id) {
            return;
        }

        // Se o usuário não for dono do comentário nem da publicação, não pode
        return $this->response(403, [], 'Você não tem permissão para deletar este comentário');
    }

    /**
     * @OA\Get(
     *     path="/comments/post/{postID}",
     *     summary="Lista todas as publicações de um postagem",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="E-mail do usuário - Usado como login",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         description="Senha do usuário",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="postID",
     *         in="path",
     *         description="ID do post",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *     )
     * )
     * Retorna todas as publicações de um Post
     *
     * @param integer $userID
     * @return JsonResponse
     */
    public function indexByPost(int $postID): JsonResponse
    {
        // A ideia da ordenação é usar 
        $comments = Comment::where('post_id', $postID)
            ->orderByRaw('IFNULL(highlight_up >= NOW(), 0) * coins DESC')
            ->orderBy('created_at', 'DESC')
            ->paginate(config('database.pageSize'))
            ->toArray();

        return $this->returnComments($comments);
    }

    /**
     * @OA\Get(
     *     path="/comments/user/{userID}",
     *     summary="Lista todas as publicações de um usuário",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="E-mail do usuário - Usado como login",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         description="Senha do usuário",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="userID",
     *         in="path",
     *         description="ID do user",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *     )
     * )
     * Retorna todas as publicações de um User
     *
     * @param integer $userID
     * @return JsonResponse
     */
    public function indexByUser(int $userID): JsonResponse
    {
        $comments = Comment::where('user_id', $userID)
            ->orderByRaw('IFNULL(highlight_up >= NOW(), 0) * coins DESC')
            ->orderBy('created_at', 'DESC')
            ->paginate(config('database.pageSize'))
            ->toArray();

        return $this->returnComments($comments);
    }

    /**
     * A partir de um array de commentários, retorna seu JSON
     *
     * @param array $commentsPaginated
     * @return JsonResponse
     */
    private function returnComments(array $commentsPaginated): JsonResponse
    {
        $data       = $commentsPaginated['data'];
        $pagination = Arr::except($commentsPaginated, 'data');

        return $this->response(200, compact(['data', 'pagination']));
    }

    /**
     * @OA\Delete(
     *     path="/comments/post/{postID}",
     *     summary="Deleta TODOS os comentários de uma postagem",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="E-mail do usuário - Usado como login",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         description="Senha do usuário",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="postID",
     *         in="path",
     *         description="ID do post",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Autorização negada"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro ao deletar"
     *     )
     * )
     * Função para deletar TODOS os comentários de um post
     *
     * @param integer $postID
     * @return JsonResponse
     */
    public function deleteByPost(int $postID): JsonResponse
    {
        $post = Post::find($postID);

        // Se o post não for do usuário, ele não pode deletar seus comentários
        if ($post->user_id != $this->user->id) {
            return $this->response(403, [], 'Você não pode deletar os comentários deste post');
        }

        $deleted = Comment::where('post_id', $post->id)->delete();
        if (!$deleted) {
            return $this->response(500, [], 'Problemas ao deletar os comentários deste post');
        }

        return $this->response(200);
    }

    /**
     * @OA\Delete(
     *     path="/comments/{postID}/{userID}",
     *     summary="Deleta TODOS os comentários de um usuário em uma postagem",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="E-mail do usuário - Usado como login",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         description="Senha do usuário",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="postID",
     *         in="path",
     *         description="ID do post",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="userID",
     *         in="path",
     *         description="ID do user",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Autorização negada"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro ao deletar"
     *     )
     * )
     * Função para deletar TODOS os comentários de um usuário em um post
     *
     * @param integer $postID
     * @return JsonResponse
     */
    public function deleteByPostAndUser(int $postID, int $userID): JsonResponse
    {
        $post = Post::find($postID);

        // Se o post não for do usuário, ele não pode deletar seus comentários
        if ($post->user_id != $this->user->id) {
            return $this->response(403, [], 'Você não pode deletar os comentários deste post');
        }

        $deleted = Comment::where('post_id', $postID)->where('user_id', $userID)->delete();
        if (!$deleted) {
            return $this->response(500, [], 'Problemas ao deletar os comentários deste post');
        }

        return $this->response(200);
    }

    /**
     * Cria uma notificação e retorna seu ID
     *
     * @param Comment $comment
     * @return integer
     */
    private function createNotification(Comment $comment): int
    {
        $url    = config('services.user.host');
        $secret = config('services.user.secret');
        $userService = new UserService($url, $secret);

        $url    = config('services.notification.host');
        $secret = config('services.notification.secret');
        $ntfService = new NotificationService($url, $secret, $this->user);

        $receiver = $userService->get($comment->post->user_id);
        $message  = sprintf(
            'O usuário %s comentou "%s" na Publicação "%s"',
            $receiver->name,
            $comment->content,
            $comment->post->title
        );
        return $ntfService->create($receiver->id, $receiver->email, $message);
    }

    /**
     * Deleta uma notificação no Serviço de notificações
     *
     * @param integer $notificationID
     * @return boolean
     */
    private function deleteNotification(int $notificationID): bool
    {
        $url    = config('services.notification.host');
        $secret = config('services.notification.secret');
        $ntfService = new NotificationService($url, $secret, $this->user);
        return $ntfService->delete($notificationID);
    }

    /**
     * Verifica se um usuário pode comentar em uma publicação
     *
     * @param integer $postID
     * @param integer $usingCoins
     * @return boolean
     */
    private function canComment(int $postID, int $usingCoins): bool
    {
        // Usuários que estão usando coins podem comentar qualquer post
        if ($usingCoins) {
            return true;
        }
        
        // Usuários assinantes podem comentar em qualquer post
        if ($this->user->subscriber) {
            return true;
        }

        // Busco o post
        $post = Post::where('id', $postID)->first('user_id');

        // E verifico se seu usuário é um assinante
        $url    = config('services.user.host');
        $secret = config('services.user.secret');
        $isPostOfSubscriber = (new UserService($url, $secret))->isSubscriber($post->user_id);

        // Posts de assinantes podem receber comentário de qualquer usuário
        return $isPostOfSubscriber;
    }

    /**
     * Busca quantos comentários o usuário fez e verifica se ele ainda pode comentar
     *
     * @return boolean
     */
    private function madeTooManyComments(): bool
    {
        // Para todo comentário feito, é criada uma key no Redis. Com isso,
        // consigo buscar todas as keys que seguem o padrão e conta-las
        $key = sprintf('comment-%d-*', $this->user->id);
        $keys = Redis::keys($key);

        // Número de comentários que podem ser feitos em um período de tempo
        $commentsPerTime = config('app.commentsPerTime');

        // Retorno se o número de comentários feito é maior que o permitido
        return count($keys) >= $commentsPerTime;
    }

    /**
     * Verifica se um usuário tem saldo para dar destaque a um comentário
     *
     * @param integer $coins
     * @return boolean
     */
    private function canHighlight(int $coins)
    {
        $url    = config('services.transaction.host');
        $secret = config('services.transaction.secret');
        $transactionService = new TransactionService($url, $secret, $this->user);

        $userBalance = $transactionService->getBalance($coins);
        return $userBalance >= $coins;
    }

    /**
     * OpenAPI - Docs - Endpoints in BaseCRUD
     * @OA\Delete(
     *     path="/comments/{commentID}",
     *     summary="Deleta um comentário",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="E-mail do usuário - Usado como login",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         description="Senha do usuário",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="commentID",
     *         in="path",
     *         description="ID do comentário",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Autorização negada"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro ao deletar"
     *     )
     * )
     ***************************************************************************
     * @OA\Post(
     *     path="/comments",
     *     summary="Cria um comentário em uma postagem",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="E-mail do usuário - Usado como login",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         description="Senha do usuário",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="post_id",
     *         in="query",
     *         description="ID do post",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="coins",
     *         in="query",
     *         description="Coins usadas para dar destaque ao comentário",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="title",
     *         in="query",
     *         description="Título do comentário",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="content",
     *         in="query",
     *         description="Conteúdo do comentário",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Dados inválidos"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Autorização negada"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro ao criar"
     *     )
     * )
     */
}
