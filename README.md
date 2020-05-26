# eSapiens Challenge
Projeto criado para o desafio técnico da eSapiens

---
## Install
Para a instalação, há um script responsável por faze-la, para executa-lo:
```bash
    cd ./scripts && ./install
```

A instalação também pode ser feita manualmente, para isso:
1) Para começar, é necessário *criar os containers* para o projeto, para isso: 
```bash
    cd ./docker/mysql && docker-compose up -d
```

2) Crie um banco de dados para cada um dos services, ou seja, 
```bash
    mysql -u $DB_USERNAME -h $DB_HOST -P $DB_PORT -c "CREATE DATABASE $SERVICE CHARACTER SET utf8
```
Por exemplo, para o service de users: 
```bash
    mysql -u $DB_USERNAME -h $DB_HOST -P $DB_PORT -c "CREATE DATABASE users CHARACTER SET utf8
```

3) Em cada service, configure os arquivos *.env*, que são responsáveis pelas configurações. Há um exemplo em .env.example  

4) *Crie as tabelas* de cada service. Executando:
```bash
    cd ./services/$SERVICE && php artisan migrate
```

---
## MySQL
O MySQL foi escohido como banco do projeto por seu desempenho em pequenos projetos, porém caso seja, necessário é possível troca-lo com extrema facilidade, pois todas as querys foram "escritas" com o Eloquent (ORM - Object Relational Mapping).
No projeto, foi criado um banco para cada service, porém esses bancos rodam no mesmo server. Em um cenário de produção, o ideal seria os bancos estivessem em services separados, permitindo uma melhor escalabilidade
  
Dados de acesso:
* User: root
* Password: 1234
* Port: 3307
  
Acesso via mysql-client:
```bash
    mysql -u root -p -h localhost -P 3307
```

---
## Redis
O Redis foi utilizado diante da necessidade de *controle de número de comentários* por intervalo de tempo. De forma que cada comentário gere uma key no redis (com o padrão "comment-$userID-$postID-$commentID") com um TTL (Time To Live). Então, antes da criação do comentário, é verifica se o número de keys (com o padrão "comment-$userID-*") excede o limite de comentários por intervalo de tempo do sistema. O Redis foi utilizado principalmente por ser um banco de dados in-memory, ou seja, salva seus dados em memória principal e com isso há um ganho enorme em desempenho.  
Dados de acesso:
* Port: 6397
  
Acesso via redis-client:
```bash
    redis-cli -h localhost -p 6397
```

---
## Database Seeds
Como não foi implementado a parte de criação de posts, é *necessário* que seja executado o seed no service de *comments*, para isso:
```bash
    cd ./services/comments && php artisan db:seed --class PostSeeder
```
caso opte por também popular os Comments, execute: 
```bash
    cd ./services/comments && php artisan db:seed
```

Existem seeds para todos os outros services, para executá-los:
* Users 
```bash
    cd ./services/users && php artisan db:seed
```

* Transactions 
```bash
    cd ./services/transactions && php artisan db:seed
```

* Notifications 
```bash
    cd ./services/notifications && php artisan db:seed
```

---
## Start
Para startar o projeto, primeiro é necessário:
1) Dar *start nos containers*, para isso: `cd ./docker/mysql && docker-compose start && cd ../redis && docker-compose start`
2) E então, *criar um server para cada service*. Existem diversas formas para isso, na minha opinião, em ambiente de desenvolvimento a melhor é utilizando o servidor embutido do PHP, isso pois há ganhos de desempenho pela não utilização do Docker (principalmente no MacOS). Para isso:
```bash
    cd ./services/$SERVICE && php -S http://localhost:$SERVICE_PORT
```

---
## Tests
Cada service tem seus testes independentes, para executa-los:  
1) Acesse a pasta do service, e execute o *phpunit*
```bash
    cd ./services/users && ./vendor/bin/phpunit
```
  
Há um arquivo que executa os testes, para executa-lo:
```bash
    cd scripts && ./test
```
  
É importante que antes de executar os testes, seja criado um banco de dados com o nome "testing"  
Para criar o banco de dados, execute:
```bash
    mysql -u $DB_USERNAME -h $DB_HOST -P $DB_PORT -c "CREATE DATABASE testing CHARACTER SET utf8;"
```

---
## Docs
As [docs](http://localhost:4010/docs) podem ser acessadas pelo endpoint /docs

As docs são feitas com a estrutura [OpenApi](https://github.com/OAI/OpenAPI-Specification) e são salvas nos controllers de cada Service, com isso é necessário atualiza-las após alterações em qualquer controller. Para atualiza-las
```bash
    cd ./scripts && ./docs-generate
```

Na pasta /docs/requests, existem arquivos .http feitos para a extensão [RestClient](https://marketplace.visualstudio.com/items?itemName=humao.rest-client) do VSCode
Os arquivos contêm exemplos de requests que podem ser feitos pelo Gateway. Também é possível visualizar esses arquivos (com todos os endpoints do service) dentro da pasta docs de cada service

---
## Links
[GitHub](https://github.com/guilhermefalves/esapiens-challenge) - GitHub do projeto
[BaseCRUD](https://github.com/guilhermefalves/lumen-base-crud) - CRUD básico para facilitar na criação de diferentes services

## Tecnologias
[Lumen](https://lumen.laravel.com/docs/7.x) - Framework base para todos os services
[MySQL](https://dev.mysql.com/doc/refman/8.0/en/) - Banco de dados usado na persistência dos dados, porém pode ser trocado com facilidade
[Redis](https://redis.io/documentation) - Bando de dados de cache usado no service de comments para contar quantos comentários o usuário fez no intervalo de tempo
[Docker](https://docs.docker.com/) - Containerização de alguns serviços do projeto
[DockerCompose](https://docs.docker.com/compose/compose-file/) - Ferramenta para facilitar a criação de containers no projeto
[PHPUnit](https://phpunit.readthedocs.io/pt_BR/latest/) - Framework usado nos testes unitários do projeto
[Trello](https://trello.com/b/s1p0SFvE/auxilium5) - Organização das tarefas do projeto (em quadros)
[Swagger](https://swagger.io/docs/) - Documentação do projeto
[OpenAPI](https://github.com/OAI/OpenAPI-Specification)

---
## Author
Guilherme Felipe Alves

[E-mail](mailto:guihalves20@gmail.com)
[GitHub](https://github.com/guilhermefalves)
[Linkedin](https://www.linkedin.com/public-profile/settings?trk=d_flagship3_profile_self_view_public_profile&lipi=urn%3Ali%3Apage%3Ad_flagship3_profile_self_edit_top_card%3Bc3m143rTSt2vaRP0rafeEw%3D%3D)

---
## License
GNU General Public License v3.0 or later

See [LICENSE](https://github.com/guilhermefalves/esapiens-challenge/blob/master/LICENSE.md) to see the full text