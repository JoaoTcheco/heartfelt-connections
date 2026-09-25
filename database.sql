-- ============================================================
-- FarmaPonto - Base de Dados Unica (MySQL 8 / MariaDB 10.4+)
-- ============================================================
-- Ficheiro unico e idempotente: pode ser importado varias vezes
-- sem destruir dados existentes (usa CREATE TABLE IF NOT EXISTS
-- e INSERT ... ON DUPLICATE KEY UPDATE nas seeds).
--
-- Importar:
--   mysql -u root -p < database.sql
--   (ou phpMyAdmin -> Importar)
--
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- Fuso horario da aplicacao: Africa/Maputo (+02:00)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `farmaponto`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `farmaponto`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 1. funcionarios
--    Entidade central: colaborador + credenciais + parametros
--    salariais e de horario usados pelo motor de calculo.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `funcionarios` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo`         VARCHAR(32)  NOT NULL COMMENT 'Codigo interno unico (ex.: FUNC001)',
  `nome`           VARCHAR(150) NOT NULL,
  `email`          VARCHAR(190) NOT NULL,
  `cargo`          VARCHAR(80)  NOT NULL DEFAULT 'Funcionario',
  `password_hash`  VARCHAR(255) NOT NULL COMMENT 'bcrypt cost 12',
  `pin_hash`       VARCHAR(255) NOT NULL COMMENT 'bcrypt cost 12 (QuickPunch)',
  `pin_cifrado`      VARCHAR(255) NULL COMMENT 'PIN actual cifrado AES-256-CBC (consulta admin)',
  `password_cifrada` VARCHAR(255) NULL COMMENT 'Password actual cifrada AES-256-CBC (consulta admin)',
  `perfil`         ENUM('admin','gestor','funcionario') NOT NULL DEFAULT 'funcionario',
  `marca_ponto`    TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = entra na assiduidade (ponto, faltas, salarios); 0 = utilizador administrativo',
  `salario_base`   DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Salario mensal bruto',
  `dias_uteis_mes` TINYINT UNSIGNED NOT NULL DEFAULT 22,
  `dias_trabalho`  VARCHAR(20)  NOT NULL DEFAULT '1,2,3,4,5' COMMENT 'Dias da semana ISO (1=Seg ... 7=Dom)',
  `salario_diario` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `salario_hora`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `salario_minuto` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `valor_hora_extra` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor pago por cada hora extra',

  `carga_diaria`   DECIMAL(4,2)  NOT NULL DEFAULT 8.00 COMMENT 'Horas contratadas por dia',
  `hora_entrada`   TIME NOT NULL DEFAULT '08:00:00',
  `hora_saida`     TIME NOT NULL DEFAULT '17:00:00',
  `ativo`          TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_funcionarios_codigo` (`codigo`),
  UNIQUE KEY `uq_funcionarios_email`  (`email`),
  KEY `ix_funcionarios_ativo`  (`ativo`),
  KEY `ix_funcionarios_perfil` (`perfil`),
  KEY `ix_funcionarios_marca_ponto` (`marca_ponto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. selfies
--    Prova fotografica da marcacao. Ficheiro em storage/selfies,
--    metadados (hash, bytes) aqui.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `selfies` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `caminho`        VARCHAR(255) NOT NULL COMMENT 'Caminho relativo a raiz do projecto',
  `hash_sha256`    CHAR(64)     NOT NULL,
  `bytes`          INT UNSIGNED NOT NULL DEFAULT 0,
  `criado_em`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_selfies_func` (`funcionario_id`),
  KEY `ix_selfies_hash` (`hash_sha256`),
  CONSTRAINT `fk_selfies_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. registos
--    Livro de ponto. Um registo por evento.
--    tipo: entrada | saida | ferias | falta
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registos` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `tipo`           ENUM('entrada','saida','ferias','falta') NOT NULL,
  `marcado_em`     DATETIME NOT NULL,
  `ip`             VARBINARY(16) NULL COMMENT 'INET6_ATON()',
  `user_agent`     VARCHAR(255) NULL,
  `selfie_id`      BIGINT UNSIGNED NULL,
  `metodo`         VARCHAR(20) NOT NULL DEFAULT 'painel' COMMENT 'painel|pin|digital|sistema',
  `metodo_detalhe` VARCHAR(60) NULL COMMENT 'ex.: dedo usado na impressao digital',
  `observacao`     VARCHAR(255) NULL,
  `criado_em`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_registos_func_data` (`funcionario_id`, `marcado_em`),
  KEY `ix_registos_tipo`      (`tipo`),
  KEY `ix_registos_data`      (`marcado_em`),
  KEY `ix_registos_selfie`    (`selfie_id`),
  KEY `ix_registos_metodo`    (`metodo`),
  CONSTRAINT `fk_registos_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_registos_selfie`
    FOREIGN KEY (`selfie_id`) REFERENCES `selfies` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. funcionario_dias_trabalho
--    Calendario de trabalho especifico por mes (YYYY-MM).
--    Sobrepoe-se a funcionarios.dias_trabalho quando existe.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `funcionario_dias_trabalho` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `ano_mes`        CHAR(7) NOT NULL COMMENT 'YYYY-MM',
  `dias`           JSON NOT NULL COMMENT 'Array de dias do mes, ex.: [1,2,3,8]',
  `criado_em`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fdt_func_mes` (`funcionario_id`, `ano_mes`),
  CONSTRAINT `fk_fdt_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4.1 dia_ajustes
--    Excepcoes lancadas pelo gestor sobre um dia concreto:
--    falta justificada (conta como presenca e tem vencimento),
--    folga / licenca / ferias (com ou sem vencimento) e horas extras.
--    1 linha por (funcionario_id, dia).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dia_ajustes` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `dia`            DATE NOT NULL,
  `tipo`           ENUM('falta_justificada','folga','licenca','ferias','normal')
                   NOT NULL DEFAULT 'normal',
  `com_vencimento` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = dia pago',
  `horas_extra`    DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `observacao`     VARCHAR(255) NULL,
  `criado_por`     INT UNSIGNED NULL,
  `criado_em`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dia_ajustes` (`funcionario_id`, `dia`),
  KEY `ix_dia_ajustes_dia` (`dia`),
  CONSTRAINT `fk_dia_ajustes_func`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. logs

--    Trilho de auditoria (quem fez o que, quando, sobre o que).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NULL COMMENT 'NULL = accao anonima/sistema',
  `acao`           VARCHAR(60)  NOT NULL,
  `entidade`       VARCHAR(60)  NULL,
  `entidade_id`    VARCHAR(60)  NULL,
  `detalhes`       JSON NULL,
  `ip`             VARBINARY(16) NULL COMMENT 'INET6_ATON()',
  `criado_em`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_logs_func`  (`funcionario_id`),
  KEY `ix_logs_acao`  (`acao`),
  KEY `ix_logs_data`  (`criado_em`),
  CONSTRAINT `fk_logs_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. password_resets
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `token`          CHAR(64) NOT NULL COMMENT 'hash do token enviado por email',
  `expira_em`      DATETIME NOT NULL,
  `usado`          TINYINT(1) NOT NULL DEFAULT 0,
  `criado_em`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_token` (`token`),
  KEY `ix_pr_func` (`funcionario_id`),
  KEY `ix_pr_exp`  (`expira_em`),
  CONSTRAINT `fk_pr_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. rate_limits
--    Protecao de forca-bruta (login, QuickPunch).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chave`     VARCHAR(100) NOT NULL COMMENT 'ex.: login:email, quickpunch',
  `ip`        VARBINARY(16) NOT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_rl_chave_ip_data` (`chave`, `ip`, `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. config
--    Definicoes editaveis em /configuracoes (chave-valor).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `config` (
  `chave`         VARCHAR(80) NOT NULL,
  `valor`         TEXT NOT NULL,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. relatorio_modelos
--    Modelos (presets) de filtros de calculo de salario.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `relatorio_modelos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`       VARCHAR(100) NOT NULL,
  `filtros`    JSON NOT NULL COMMENT 'Combinacao de filtros do motor de calculo',
  `criado_por` INT UNSIGNED NULL,
  `criado_em`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rm_nome` (`nome`),
  KEY `ix_rm_autor` (`criado_por`),
  CONSTRAINT `fk_rm_funcionario`
    FOREIGN KEY (`criado_por`) REFERENCES `funcionarios` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 10. folhas_salariais / folha_salarial_itens
--     Fecho (snapshot imutavel) de um calculo de salarios.
--     Guardar uma folha NAO impede recalcular: /relatorios continua
--     a recalcular a pedido; a folha e apenas o registo do aprovado.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `folhas_salariais` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referencia`  VARCHAR(20) NOT NULL COMMENT 'Ref. curta do documento',
  `periodo_de`  DATE NOT NULL,
  `periodo_ate` DATE NOT NULL,
  `base`        VARCHAR(20) NOT NULL DEFAULT 'mes_completo' COMMENT 'mes_completo|proporcional|ate_dia',
  `filtros`     JSON NOT NULL COMMENT 'Filtros aplicados no motor de calculo',
  `totais`      JSON NOT NULL COMMENT 'Totais agregados (salario, cortes, liquido)',
  `estado`      ENUM('fechada','reaberta') NOT NULL DEFAULT 'fechada',
  `observacao`  VARCHAR(255) NULL,
  `criado_por`  INT UNSIGNED NULL,
  `criado_em`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fs_ref` (`referencia`),
  KEY `ix_fs_periodo` (`periodo_de`, `periodo_ate`),
  KEY `ix_fs_autor` (`criado_por`),
  CONSTRAINT `fk_fs_funcionario`
    FOREIGN KEY (`criado_por`) REFERENCES `funcionarios` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `folha_salarial_itens` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folha_id`       INT UNSIGNED NOT NULL,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `nome`           VARCHAR(120) NOT NULL COMMENT 'Nome a data do fecho (historico)',
  `cargo`          VARCHAR(80) NULL,
  `salario`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_corte`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `liquido`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `dados`          JSON NOT NULL COMMENT 'Linha completa do motor de calculo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fsi_folha_func` (`folha_id`, `funcionario_id`),
  KEY `ix_fsi_func` (`funcionario_id`),
  CONSTRAINT `fk_fsi_folha`
    FOREIGN KEY (`folha_id`) REFERENCES `folhas_salariais` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fsi_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEEDS (idempotentes)
-- ============================================================

INSERT INTO `config` (`chave`, `valor`) VALUES
  ('nome_empresa',           'FarmaPonto'),
  ('instituicao',            'FarmaPonto - Gestao de Assiduidade'),
  ('moeda',                  'MZN'),
  ('tolerancia_atraso_min',  '10'),
  ('ferias_max_dias_ano',    '30'),
  ('ferias_min_aviso_dias',  '0'),
  ('ferias_permitir_passado','1'),
  ('auto_registo_aberto',    '0'),
  ('rate_limit_login',       '5'),
  ('rate_limit_quickpunch',  '10'),
  ('relatorio_nota_rodape',  'Documento gerado automaticamente pelo FarmaPonto.'),
  ('instituicao_logo',       ''),
  ('bloqueio_tentativas',    '0'),
  ('selfies_retencao_meses', '0')
ON DUPLICATE KEY UPDATE `chave` = VALUES(`chave`);

INSERT INTO `relatorio_modelos` (`nome`, `filtros`) VALUES
  ('Mes completo',
   '{"periodo":"mes","descontar_faltas":1,"descontar_atrasos":1,"descontar_saida_antecipada":0,"base":"integral"}'),
  ('Primeira quinzena (1-15)',
   '{"periodo":"intervalo_dias","dia_inicio":1,"dia_fim":15,"descontar_faltas":1,"descontar_atrasos":1,"descontar_saida_antecipada":0,"base":"proporcional"}'),
  ('Segunda quinzena (16-fim)',
   '{"periodo":"intervalo_dias","dia_inicio":16,"dia_fim":31,"descontar_faltas":1,"descontar_atrasos":1,"descontar_saida_antecipada":0,"base":"proporcional"}'),
  ('Apenas faltas (sem atrasos)',
   '{"periodo":"mes","descontar_faltas":1,"descontar_atrasos":0,"descontar_saida_antecipada":0,"base":"integral"}')
ON DUPLICATE KEY UPDATE `nome` = VALUES(`nome`);

-- ============================================================
-- Administrador inicial
-- ------------------------------------------------------------
-- Crie-o pela pagina /install.php (recomendado: gera hashes bcrypt).
-- Alternativa manual (password: admin123 | PIN: 1234):
--
-- INSERT INTO funcionarios (codigo, nome, email, cargo, password_hash, pin_hash, perfil, ativo)
-- VALUES ('ADMIN001','Administrador','admin@farmaponto.mz','Administrador',
--   '$2y$12$e0MYzXyjpJS7Pd0RVvHwHeFYyettDsSs/vLoyRPUw3hIbY/Rz6.7q',
--   '$2y$12$e0MYzXyjpJS7Pd0RVvHwHeFYyettDsSs/vLoyRPUw3hIbY/Rz6.7q',
--   'admin', 1);
-- ============================================================

-- ------------------------------------------------------------
-- 14. funcionario_digitais
--     Impressoes digitais registadas (ate 3 dedos por funcionario).
--     Guarda apenas o HMAC-SHA256 do template lido pelo leitor,
--     nunca a imagem nem o template em claro.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `funcionario_digitais` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `funcionario_id` INT UNSIGNED NOT NULL,
  `slot`           TINYINT UNSIGNED NOT NULL COMMENT '1..3',
  `dedo`           VARCHAR(40) NOT NULL DEFAULT 'Indicador direito',
  `origem`         ENUM('leitor','dispositivo') NOT NULL DEFAULT 'leitor',
  `template_hash`  CHAR(64) NOT NULL,
  `criado_em`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_uso`     DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_digital_slot` (`funcionario_id`, `slot`),
  UNIQUE KEY `uq_digital_hash` (`template_hash`),
  CONSTRAINT `fk_digitais_funcionario`
    FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
