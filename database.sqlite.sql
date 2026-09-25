-- FarmaPonto - esquema SQLite (GERADO por scripts/gerar-esquema-sqlite.php)
-- Nao editar a mao: editar database.sql e voltar a gerar.
PRAGMA foreign_keys = OFF;
BEGIN;
CREATE TABLE IF NOT EXISTS "funcionarios" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "codigo" TEXT NOT NULL,
  "nome" TEXT NOT NULL,
  "email" TEXT NOT NULL,
  "cargo" TEXT NOT NULL DEFAULT 'Funcionario',
  "password_hash" TEXT NOT NULL,
  "pin_hash" TEXT NOT NULL,
  "pin_cifrado" TEXT NULL,
  "password_cifrada" TEXT NULL,
  "perfil" TEXT NOT NULL DEFAULT 'funcionario',
  "marca_ponto" INTEGER NOT NULL DEFAULT 1,
  "salario_base" NUMERIC NOT NULL DEFAULT 0.00,
  "dias_uteis_mes" INTEGER NOT NULL DEFAULT 22,
  "dias_trabalho" TEXT NOT NULL DEFAULT '1,2,3,4,5',
  "salario_diario" NUMERIC NOT NULL DEFAULT 0.00,
  "salario_hora" NUMERIC NOT NULL DEFAULT 0.00,
  "salario_minuto" NUMERIC NOT NULL DEFAULT 0.0000,
  "valor_hora_extra" NUMERIC NOT NULL DEFAULT 0.00,
  "carga_diaria" NUMERIC NOT NULL DEFAULT 8.00,
  "hora_entrada" TEXT NOT NULL DEFAULT '08:00:00',
  "hora_saida" TEXT NOT NULL DEFAULT '17:00:00',
  "ativo" INTEGER NOT NULL DEFAULT 1,
  "criado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  "atualizado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_funcionarios_codigo" ON "funcionarios" ("codigo");
CREATE UNIQUE INDEX IF NOT EXISTS "uq_funcionarios_email" ON "funcionarios" ("email");
CREATE INDEX IF NOT EXISTS "ix_funcionarios_ativo" ON "funcionarios" ("ativo");
CREATE INDEX IF NOT EXISTS "ix_funcionarios_perfil" ON "funcionarios" ("perfil");
CREATE INDEX IF NOT EXISTS "ix_funcionarios_marca_ponto" ON "funcionarios" ("marca_ponto");
CREATE TRIGGER IF NOT EXISTS "trg_funcionarios_atualizado_em" AFTER UPDATE ON "funcionarios" FOR EACH ROW BEGIN UPDATE "funcionarios" SET "atualizado_em" = MYSQL_NOW() WHERE rowid = NEW.rowid; END;
CREATE TABLE IF NOT EXISTS "selfies" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "funcionario_id" INTEGER NOT NULL,
  "caminho" TEXT NOT NULL,
  "hash_sha256" TEXT NOT NULL,
  "bytes" INTEGER NOT NULL DEFAULT 0,
  "criado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  FOREIGN KEY ("funcionario_id") REFERENCES "funcionarios" ("id")
    ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS "ix_selfies_func" ON "selfies" ("funcionario_id");
CREATE INDEX IF NOT EXISTS "ix_selfies_hash" ON "selfies" ("hash_sha256");
CREATE TABLE IF NOT EXISTS "registos" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "funcionario_id" INTEGER NOT NULL,
  "tipo" TEXT NOT NULL,
  "marcado_em" TEXT NOT NULL,
  "ip" BLOB NULL,
  "user_agent" TEXT NULL,
  "selfie_id" INTEGER NULL,
  "metodo" TEXT NOT NULL DEFAULT 'painel',
  "metodo_detalhe" TEXT NULL,
  "observacao" TEXT NULL,
  "criado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  FOREIGN KEY ("funcionario_id") REFERENCES "funcionarios" ("id")
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY ("selfie_id") REFERENCES "selfies" ("id")
    ON DELETE SET NULL ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS "ix_registos_func_data" ON "registos" ("funcionario_id", "marcado_em");
CREATE INDEX IF NOT EXISTS "ix_registos_tipo" ON "registos" ("tipo");
CREATE INDEX IF NOT EXISTS "ix_registos_data" ON "registos" ("marcado_em");
CREATE INDEX IF NOT EXISTS "ix_registos_selfie" ON "registos" ("selfie_id");
CREATE INDEX IF NOT EXISTS "ix_registos_metodo" ON "registos" ("metodo");
CREATE TABLE IF NOT EXISTS "funcionario_dias_trabalho" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "funcionario_id" INTEGER NOT NULL,
  "ano_mes" TEXT NOT NULL,
  "dias" TEXT NOT NULL,
  "criado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  "atualizado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  FOREIGN KEY ("funcionario_id") REFERENCES "funcionarios" ("id")
    ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_fdt_func_mes" ON "funcionario_dias_trabalho" ("funcionario_id", "ano_mes");
CREATE TRIGGER IF NOT EXISTS "trg_funcionario_dias_trabalho_atualizado_em" AFTER UPDATE ON "funcionario_dias_trabalho" FOR EACH ROW BEGIN UPDATE "funcionario_dias_trabalho" SET "atualizado_em" = MYSQL_NOW() WHERE rowid = NEW.rowid; END;
CREATE TABLE IF NOT EXISTS "dia_ajustes" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "funcionario_id" INTEGER NOT NULL,
  "dia" TEXT NOT NULL,
  "tipo" TEXT NOT NULL DEFAULT 'normal',
  "com_vencimento" INTEGER NOT NULL DEFAULT 1,
  "horas_extra" NUMERIC NOT NULL DEFAULT 0.00,
  "observacao" TEXT NULL,
  "criado_por" INTEGER NULL,
  "criado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  "atualizado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  FOREIGN KEY ("funcionario_id") REFERENCES "funcionarios" ("id")
    ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_dia_ajustes" ON "dia_ajustes" ("funcionario_id", "dia");
CREATE INDEX IF NOT EXISTS "ix_dia_ajustes_dia" ON "dia_ajustes" ("dia");
CREATE TRIGGER IF NOT EXISTS "trg_dia_ajustes_atualizado_em" AFTER UPDATE ON "dia_ajustes" FOR EACH ROW BEGIN UPDATE "dia_ajustes" SET "atualizado_em" = MYSQL_NOW() WHERE rowid = NEW.rowid; END;
CREATE TABLE IF NOT EXISTS "logs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "funcionario_id" INTEGER NULL,
  "acao" TEXT NOT NULL,
  "entidade" TEXT NULL,
  "entidade_id" TEXT NULL,
  "detalhes" TEXT NULL,
  "ip" BLOB NULL,
  "criado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  FOREIGN KEY ("funcionario_id") REFERENCES "funcionarios" ("id")
    ON DELETE SET NULL ON UPDATE CASCADE
);
CREATE INDEX IF NOT EXISTS "ix_logs_func" ON "logs" ("funcionario_id");
CREATE INDEX IF NOT EXISTS "ix_logs_acao" ON "logs" ("acao");
CREATE INDEX IF NOT EXISTS "ix_logs_data" ON "logs" ("criado_em");
CREATE TABLE IF NOT EXISTS "password_resets" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "funcionario_id" INTEGER NOT NULL,
  "token" TEXT NOT NULL,
  "expira_em" TEXT NOT NULL,
  "usado" INTEGER NOT NULL DEFAULT 0,
  "criado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  FOREIGN KEY ("funcionario_id") REFERENCES "funcionarios" ("id")
    ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_pr_token" ON "password_resets" ("token");
CREATE INDEX IF NOT EXISTS "ix_pr_func" ON "password_resets" ("funcionario_id");
CREATE INDEX IF NOT EXISTS "ix_pr_exp" ON "password_resets" ("expira_em");
CREATE TABLE IF NOT EXISTS "rate_limits" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "chave" TEXT NOT NULL,
  "ip" BLOB NOT NULL,
  "criado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS "ix_rl_chave_ip_data" ON "rate_limits" ("chave", "ip", "criado_em");
CREATE TABLE IF NOT EXISTS "config" (
  "chave" TEXT NOT NULL,
  "valor" TEXT NOT NULL,
  "atualizado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  PRIMARY KEY ("chave")
);
CREATE TRIGGER IF NOT EXISTS "trg_config_atualizado_em" AFTER UPDATE ON "config" FOR EACH ROW BEGIN UPDATE "config" SET "atualizado_em" = MYSQL_NOW() WHERE rowid = NEW.rowid; END;
CREATE TABLE IF NOT EXISTS "relatorio_modelos" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "nome" TEXT NOT NULL,
  "filtros" TEXT NOT NULL,
  "criado_por" INTEGER NULL,
  "criado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  "atualizado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  FOREIGN KEY ("criado_por") REFERENCES "funcionarios" ("id")
    ON DELETE SET NULL ON UPDATE CASCADE
);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_rm_nome" ON "relatorio_modelos" ("nome");
CREATE INDEX IF NOT EXISTS "ix_rm_autor" ON "relatorio_modelos" ("criado_por");
CREATE TRIGGER IF NOT EXISTS "trg_relatorio_modelos_atualizado_em" AFTER UPDATE ON "relatorio_modelos" FOR EACH ROW BEGIN UPDATE "relatorio_modelos" SET "atualizado_em" = MYSQL_NOW() WHERE rowid = NEW.rowid; END;
CREATE TABLE IF NOT EXISTS "folhas_salariais" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "referencia" TEXT NOT NULL,
  "periodo_de" TEXT NOT NULL,
  "periodo_ate" TEXT NOT NULL,
  "base" TEXT NOT NULL DEFAULT 'mes_completo',
  "filtros" TEXT NOT NULL,
  "totais" TEXT NOT NULL,
  "estado" TEXT NOT NULL DEFAULT 'fechada',
  "observacao" TEXT NULL,
  "criado_por" INTEGER NULL,
  "criado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  FOREIGN KEY ("criado_por") REFERENCES "funcionarios" ("id")
    ON DELETE SET NULL ON UPDATE CASCADE
);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_fs_ref" ON "folhas_salariais" ("referencia");
CREATE INDEX IF NOT EXISTS "ix_fs_periodo" ON "folhas_salariais" ("periodo_de", "periodo_ate");
CREATE INDEX IF NOT EXISTS "ix_fs_autor" ON "folhas_salariais" ("criado_por");
CREATE TABLE IF NOT EXISTS "folha_salarial_itens" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "folha_id" INTEGER NOT NULL,
  "funcionario_id" INTEGER NOT NULL,
  "nome" TEXT NOT NULL,
  "cargo" TEXT NULL,
  "salario" NUMERIC NOT NULL DEFAULT 0.00,
  "total_corte" NUMERIC NOT NULL DEFAULT 0.00,
  "liquido" NUMERIC NOT NULL DEFAULT 0.00,
  "dados" TEXT NOT NULL,
  FOREIGN KEY ("folha_id") REFERENCES "folhas_salariais" ("id")
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY ("funcionario_id") REFERENCES "funcionarios" ("id")
    ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_fsi_folha_func" ON "folha_salarial_itens" ("folha_id", "funcionario_id");
CREATE INDEX IF NOT EXISTS "ix_fsi_func" ON "folha_salarial_itens" ("funcionario_id");
INSERT OR REPLACE INTO "config" ("chave", "valor") VALUES
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
  ('selfies_retencao_meses', '0');
INSERT OR REPLACE INTO "relatorio_modelos" ("nome", "filtros") VALUES
  ('Mes completo',
   '{"periodo":"mes","descontar_faltas":1,"descontar_atrasos":1,"descontar_saida_antecipada":0,"base":"integral"}'),
  ('Primeira quinzena (1-15)',
   '{"periodo":"intervalo_dias","dia_inicio":1,"dia_fim":15,"descontar_faltas":1,"descontar_atrasos":1,"descontar_saida_antecipada":0,"base":"proporcional"}'),
  ('Segunda quinzena (16-fim)',
   '{"periodo":"intervalo_dias","dia_inicio":16,"dia_fim":31,"descontar_faltas":1,"descontar_atrasos":1,"descontar_saida_antecipada":0,"base":"proporcional"}'),
  ('Apenas faltas (sem atrasos)',
   '{"periodo":"mes","descontar_faltas":1,"descontar_atrasos":0,"descontar_saida_antecipada":0,"base":"integral"}');
CREATE TABLE IF NOT EXISTS "funcionario_digitais" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "funcionario_id" INTEGER NOT NULL,
  "slot" INTEGER NOT NULL,
  "dedo" TEXT NOT NULL DEFAULT 'Indicador direito',
  "origem" TEXT NOT NULL DEFAULT 'leitor',
  "template_hash" TEXT NOT NULL,
  "criado_em" TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  "ultimo_uso" TEXT NULL,
  FOREIGN KEY ("funcionario_id") REFERENCES "funcionarios" ("id")
    ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE UNIQUE INDEX IF NOT EXISTS "uq_digital_slot" ON "funcionario_digitais" ("funcionario_id", "slot");
CREATE UNIQUE INDEX IF NOT EXISTS "uq_digital_hash" ON "funcionario_digitais" ("template_hash");
COMMIT;
PRAGMA foreign_keys = ON;
