<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;

use LumenBaseCRUD\Controller as BaseCRUD;
use Firebase\JWT\JWT;
use App\Libraries\UserService;

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
        'title'   => 'string|max:100|required',
        'content' => 'string|required'
    ];

    protected function preStore(array &$data)
    {
        // Adiciono o id do usuário logado
        $data['user_id'] = $this->user->id;

        // Começo verificando se o usuário pode comentar
        if (!$this->canComment($data['post_id'], false)) {
            return $this->response(403, [], 'Você não pode comentar nesse post');
        }
    }

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
}
