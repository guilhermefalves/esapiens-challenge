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
use Illuminate\Support\Carbon;

/**
 * Controller das transactions
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

    protected function preStore(array &$data)
    {
        // Adiciono o id do usuário logado
        $data['user_id'] = $this->user->id;

        // Qauntas moedas estão sendo utilizadas no destaque
        $coins = (isset($data['coins'])) ? $data['coins'] : 0;

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

        $url    = config('services.notification.host');
        $secret = config('services.notification.secret');
        $ntfService     = new NotificationService($url, $secret, $this->user);
        $notificationID = $ntfService->create();
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
            $ntfService->delete($notificationID);
            return $this->response(500, [], 'Problemas confirmar o destaque para o comentário');
        }
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
     * Verifica se um usuário pode comentar em uma publicação
     *
     * @param integer $postID
     * @param integer $usingCoins
     * @return boolean
     */
    private function canComment(int $postID, int $usingCoins): bool
    {
        // TODO: verificar qtos comentários o usuário fez nos últimos minutos
        // para isso, quero setar uma keyu no redis com ttl

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
}
