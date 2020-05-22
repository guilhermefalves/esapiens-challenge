## Para iniciar qualquer container
1) [Instale o docker](https://docs.docker.com/install/)

2) É necessário ter uma Docekr Id para o download de imagens que serão utilizadas

3) Antes de iniciar qualquer serviço verifique no arquivo docker-compose.yml se o diretório de volumes está correto, devendo ser algo como:
    - /caminho/para/arquivos-do-projeto/:/var/www/html/

4) Dentro desse diretório, execute ./scripts/start-services.sh $NOME_SERVICE ou ./scripts/start-services.sh *
