# FarmaPonto — Instalação e mudança de servidor

O FarmaPonto é PHP puro com MySQL/MariaDB. **Não usa frameworks, Composer,
internet nem qualquer serviço externo.** Copiar a pasta e importar a base de
dados é tudo o que é preciso.

## 1. O que é necessário

| Requisito | Mínimo | Recomendado |
|---|---|---|
| PHP | 8.0 | 8.2 ou superior |
| Extensões PHP | pdo_mysql, mbstring, json, zip, gd (opcional) | as mesmas |
| Base de dados | MySQL 5.7 / MariaDB 10.4 | MariaDB 11 |
| Espaço em disco | 500 MB | 5 GB (fotografias e cópias) |

## 2. Instalar no Windows (XAMPP) — mais simples

1. Instalar o XAMPP e iniciar **Apache** e **MySQL** no painel.
2. Copiar a pasta `farmaponto` para `C:\xampp\htdocs\`.
3. Abrir `http://localhost/phpmyadmin`, criar a base de dados `farmaponto`
   (codificação `utf8mb4_unicode_ci`) e importar `database.sql`.
4. Abrir `http://localhost/farmaponto/install.php` e seguir os passos
   (cria o primeiro administrador).
5. Entrar em `http://localhost/farmaponto/login`.

## 3. Instalar em Linux (Apache + PHP-FPM)

```bash
sudo apt install apache2 php-fpm php-mysql php-mbstring php-zip mariadb-server
sudo a2enmod rewrite headers deflate expires proxy_fcgi setenvif
sudo cp -r farmaponto /var/www/
sudo chown -R www-data:www-data /var/www/farmaponto/storage
sudo mysql -e "CREATE DATABASE farmaponto CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql farmaponto < /var/www/farmaponto/database.sql
sudo systemctl reload apache2
```

Bloco do site (`/etc/apache2/sites-available/farmaponto.conf`):

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/farmaponto
    <Directory /var/www/farmaponto>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog  ${APACHE_LOG_DIR}/farmaponto-erro.log
    CustomLog ${APACHE_LOG_DIR}/farmaponto-acesso.log combined
</VirtualHost>
```

O `.htaccess` que acompanha o projecto já traz as regras de endereços,
segurança, compressão e cache.

## 4. Ligação à base de dados

Nunca é preciso editar `config/config.php`. Para adaptar a um servidor
diferente, criar `config/config.local.php` (já ignorado pelo Git):

```php
<?php
return [
    'db_host'      => '127.0.0.1',
    'db_port'      => 3306,
    'db_name'      => 'farmaponto',
    'db_user'      => 'farmaponto',
    'db_pass'      => 'a-sua-palavra-passe',
    'db_socket'    => null,        // ex.: '/run/mysqld/mysqld.sock'
    'timezone_sql' => '+02:00',    // Maputo
    'modo'         => 'producao',  // esconde detalhes técnicos aos utilizadores
];
```

Em alternativa, variáveis de ambiente com o prefixo `FP_`
(`FP_DB_HOST`, `FP_DB_NAME`, `FP_DB_USER`, `FP_DB_PASS`, `FP_DB_PORT`).

## 5. Mudar de computador ou de servidor

1. No sistema antigo: **Cópias de Segurança → Descarregar cópia completa**
   (ficheiro ZIP com a base de dados e as fotografias).
2. No sistema novo: instalar como acima (passos 2 ou 3).
3. **Cópias de Segurança → Restaurar** e escolher o ficheiro.
4. Confirmar em **Diagnóstico** que tudo aparece verde.

## 6. Contentor (opcional)

```bash
docker compose up -d      # aplicação em http://localhost:8080
```

Ver `Dockerfile` e `docker-compose.yml` na raiz do projecto.

## 7. Depois de instalar

- Abrir **Configurações** e definir nome da instituição, moeda e tolerância.
- Agendar a manutenção diária: `php cli/manutencao.php` (ver o próprio ficheiro).
- Correr `php tests/executar.php` — devem passar todas as verificações.
- Guardar uma cópia do ZIP completo fora do computador (pen ou disco externo).

## 8. Se algo falhar

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| Página branca | PHP sem `pdo_mysql` | instalar a extensão e reiniciar |
| "Erro de ligação" | dados da base errados | corrigir `config/config.local.php` |
| Endereços dão 404 | `mod_rewrite` desligado | `sudo a2enmod rewrite` |
| Fotografias não gravam | permissões | dar escrita em `storage/` |
| Página `/saude` a vermelho | ver a mensagem indicada | seguir a indicação de **Diagnóstico** |
