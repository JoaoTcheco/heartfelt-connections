<?php
use App\Helpers\Csrf;
$fmt = fn($v) => number_format((float)$v, 2, ',', '.');
$diasNome = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
$mesesNome = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
?>
<div class="mb-6 flex items-center justify-between no-print">
    <div>
        <a href="<?= BASE_PATH ?>/funcionarios" class="text-sm text-emerald-600 hover:text-emerald-700 mb-1 inline-block">
            <i class="fa-solid fa-arrow-left mr-1"></i> Voltar aos funcionários
        </a>
        <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($func['nome']) ?></h1>
        <p class="text-gray-500 text-sm"><?= htmlspecialchars($func['cargo']) ?> · <span class="capitalize"><?= $func['perfil'] ?></span></p>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 flex items-center gap-1.5">
            <i class="fa-solid fa-print"></i> Imprimir
        </button>
        <button onclick="imprimirMes()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 flex items-center gap-1.5">
            <i class="fa-solid fa-calendar"></i> Imprimir Mês
        </button>
        <button type="button" onclick="abrirFeriasDetalhe()" class="px-3 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600 flex items-center gap-1.5 shadow-sm">
            <i class="fa-solid fa-umbrella-beach"></i> Adicionar Férias
        </button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Coluna esquerda: dados do funcionário -->
    <div class="lg:col-span-1 space-y-4">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-user text-emerald-600"></i> Dados Pessoais
            </h2>
            <form id="form-detalhe" class="space-y-3">
                <input type="hidden" name="csrf" value="<?= Csrf::token() ?>">
                <input type="hidden" name="id" value="<?= $func['id'] ?>">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-0.5">Nome</label>
                    <input type="text" name="nome" id="det-nome" value="<?= htmlspecialchars($func['nome']) ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Email</label>
                        <input type="email" name="email" id="det-email" value="<?= htmlspecialchars($func['email']) ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Código</label>
                        <input type="text" name="codigo" id="det-codigo" value="<?= htmlspecialchars($func['codigo']) ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-0.5">Cargo</label>
                    <input type="text" name="cargo" id="det-cargo" value="<?= htmlspecialchars($func['cargo']) ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-0.5">Nova password <span class="text-gray-400 font-normal">(deixar vazio para manter)</span></label>
                    <input type="password" name="password" id="det-password" placeholder="••••••••" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-0.5">Novo PIN (4 dígitos) <span class="text-gray-400 font-normal">(deixar vazio para manter)</span></label>
                    <input type="password" name="pin" id="det-pin" maxlength="4" inputmode="numeric" pattern="\d{4}" placeholder="••••" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-emerald-800"><i class="fa-solid fa-key"></i> Credenciais em uso</span>
                        <button type="button" id="btn-ver-cred"
                            class="px-2.5 py-1 text-xs rounded-md border border-emerald-300 text-emerald-700 bg-white hover:bg-emerald-100">
                            <i class="fa-solid fa-eye"></i> Mostrar
                        </button>
                    </div>
                    <div id="cred-box" class="hidden mt-2 space-y-1 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 w-20">PIN</span>
                            <code id="cred-pin" class="font-mono tracking-widest text-gray-900">----</code>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 w-20">Password</span>
                            <code id="cred-pass" class="font-mono text-gray-900 break-all">--------</code>
                        </div>
                        <p id="cred-aviso" class="text-xs text-amber-700 hidden"></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Perfil</label>
                        <select name="perfil" id="det-perfil" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                            <option value="funcionario" <?= $func['perfil'] === 'funcionario' ? 'selected' : '' ?>>Funcionário</option>
                            <option value="gestor" <?= $func['perfil'] === 'gestor' ? 'selected' : '' ?>>Gestor</option>
                            <option value="admin" <?= $func['perfil'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Marca ponto</label>
                        <select name="marca_ponto" id="det-marca-ponto" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                            <option value="1" <?= (int) ($func['marca_ponto'] ?? 1) === 1 ? 'selected' : '' ?>>Sim</option>
                            <option value="0" <?= (int) ($func['marca_ponto'] ?? 1) === 0 ? 'selected' : '' ?>>Nao</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Estado</label>
                        <span class="inline-block mt-1.5 px-2.5 py-1 rounded-full text-xs font-medium <?= $func['ativo'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
                            <?= $func['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </div>
                </div>
                <hr class="border-gray-200">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Salário base (<?= $stats['moeda'] ?>)</label>
                        <input type="number" name="salario_base" id="det-salario" value="<?= $func['salario_base'] ?>" step="0.01" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Carga diária (h)</label>
                        <input type="number" name="carga_diaria" id="det-carga" value="<?= $func['carga_diaria'] ?? 8 ?>" step="0.5" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Entrada</label>
                        <input type="text" name="hora_entrada" id="det-hora-entrada" value="<?= substr($func['hora_entrada'] ?? '08:00', 0, 5) ?>" pattern="\d{2}:\d{2}" placeholder="HH:MM" inputmode="numeric" maxlength="5" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-0.5">Saída</label>
                        <input type="text" name="hora_saida" id="det-hora-saida" value="<?= substr($func['hora_saida'] ?? '17:00', 0, 5) ?>" pattern="\d{2}:\d{2}" placeholder="HH:MM" inputmode="numeric" maxlength="5" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-xs text-gray-500">Valor dia</p>
                        <p class="text-sm font-bold text-gray-900" id="det-salario-dia"><?= $fmt($stats['valor_dia']) ?></p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-xs text-gray-500">Valor hora</p>
                        <p class="text-sm font-bold text-gray-900" id="det-salario-hora"><?= $fmt($stats['valor_hora']) ?></p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-xs text-gray-500">Valor min</p>
                        <p class="text-sm font-bold text-gray-900" id="det-salario-minuto"><?= $fmt($func['salario_minuto'] ?? 0) ?></p>
                    </div>
                </div>
                <div class="flex justify-end pt-1">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-1.5">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </form>
        </div>

        <!-- Estatísticas do mês -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5" id="stats-mes">
            <h2 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-chart-simple text-emerald-600"></i> Estatísticas — <?= $mesesNome[(int)$mes] ?> <?= $ano ?>
            </h2>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div class="border border-gray-100 rounded-lg p-2.5">
                    <p class="text-xs text-gray-500">Dias de trabalho (decorridos / mês)</p>
                    <p class="text-lg font-bold text-gray-900"><?= $stats['dias_esperados'] ?> <span class="text-sm font-medium text-gray-400">/ <?= $stats['dias_trabalho_mes'] ?? $stats['dias_esperados'] ?></span></p>
                </div>
                <div class="border border-gray-100 rounded-lg p-2.5">
                    <p class="text-xs text-gray-500">Presenças</p>
                    <p class="text-lg font-bold text-emerald-600"><?= $stats['presencas'] ?></p>
                </div>
                <div class="border border-gray-100 rounded-lg p-2.5">
                    <p class="text-xs text-gray-500">Faltas</p>
                    <p class="text-lg font-bold <?= $stats['faltas'] > 0 ? 'text-red-600' : 'text-gray-900' ?>"><?= $stats['faltas'] ?></p>
                </div>
                <div class="border border-gray-100 rounded-lg p-2.5">
                    <p class="text-xs text-gray-500">Atrasos</p>
                    <p class="text-lg font-bold <?= $stats['atrasos'] > 0 ? 'text-amber-600' : 'text-gray-900' ?>"><?= $stats['atrasos'] ?> <span class="text-xs text-gray-400">(<?= $stats['horas_atraso'] ?>h)</span></p>
                </div>
                <div class="border border-gray-100 rounded-lg p-2.5">
                    <p class="text-xs text-gray-500">Férias</p>
                    <p class="text-lg font-bold text-blue-600"><?= $stats['dias_ferias'] ?></p>
                </div>
                <div class="border border-gray-100 rounded-lg p-2.5">
                    <p class="text-xs text-gray-500">Folgas</p>
                    <p class="text-lg font-bold text-purple-600"><?= $stats['dias_folga'] ?></p>
                </div>
                <div class="border border-gray-100 rounded-lg p-2.5">
                    <p class="text-xs text-gray-500">Corte faltas</p>
                    <p class="text-sm font-bold text-red-600"><?= $fmt($stats['corte_falta']) ?></p>
                </div>
                <div class="border border-gray-100 rounded-lg p-2.5">
                    <p class="text-xs text-gray-500">Corte atrasos</p>
                    <p class="text-sm font-bold text-amber-600"><?= $fmt($stats['corte_atraso']) ?></p>
                </div>
                <div class="col-span-2 border border-emerald-100 bg-emerald-50 rounded-lg p-2.5">
                    <p class="text-xs text-emerald-600">Líquido a receber</p>
                    <p class="text-lg font-bold text-emerald-700"><?= $fmt($stats['liquido']) ?> <span class="text-xs font-normal"><?= $stats['moeda'] ?></span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Coluna direita: calendário -->
    <div class="lg:col-span-2 space-y-4">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-4 no-print">
                <h2 class="font-semibold text-gray-900 flex items-center gap-2">
                    <i class="fa-solid fa-calendar-days text-emerald-600"></i> Calendário de Trabalho
                </h2>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="calDetalheMes(-1)" class="px-2 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50"><i class="fa-solid fa-chevron-left"></i></button>
                    <input type="month" id="det-cal-mes" value="<?= sprintf('%04d-%02d', $ano, $mes) ?>" class="px-2 py-1 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                    <button type="button" onclick="calDetalheMes(1)" class="px-2 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50"><i class="fa-solid fa-chevron-right"></i></button>
                    <button type="button" onclick="calDetalheHoje()" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">Hoje</button>
                </div>
            </div>

            <!-- Legenda -->
            <div class="flex flex-wrap gap-3 mb-3 text-xs text-gray-600">
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-emerald-500 inline-block"></span> Dia trabalho</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-gray-200 inline-block"></span> Folga</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-200 border border-green-500 inline-block"></span> Presente</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-500 inline-block"></span> Falta</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-400 inline-block"></span> Atraso</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-300 inline-block"></span> Férias</span>
            </div>

            <div class="flex items-center gap-2 mb-3 no-print">
                <div class="flex rounded-lg border border-gray-300 overflow-hidden text-xs">
                    <button type="button" id="modo-dia" onclick="calDetalheModo('dia')" class="px-3 py-1.5 font-medium bg-emerald-600 text-white">Adicionar Dia</button>
                    <button type="button" id="modo-folga" onclick="calDetalheModo('folga')" class="px-3 py-1.5 font-medium text-gray-600 hover:bg-gray-50">Adicionar Folga</button>
                </div>
                <span class="text-xs text-gray-400 ml-1">Clique num dia para <span id="modo-label">adicionar como trabalho</span></span>
                <div class="ml-auto flex gap-1">
                    <button type="button" onclick="calDetalheSelecionarTodos()" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">Todos</button>
                    <button type="button" onclick="calDetalheLimparTodos()" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">Limpar</button>
                    <button type="button" onclick="calDetalheDiasSemana()" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">Seg-Sex</button>
                </div>
            </div>
            <div class="flex items-center gap-2 mb-2 no-print">
                <span class="text-xs text-gray-500"><span id="det-dias-count">0</span> dias de trabalho selecionados</span>
                <span id="det-saving-indicator" class="text-xs text-emerald-600 hidden"><i class="fa-solid fa-spinner fa-spin"></i> a guardar...</span>
            </div>

            <div id="det-cal-grid" class="cal-grid max-w-full"></div>

            <input type="hidden" id="det-dias-mes" value="">
            <input type="hidden" id="det-dias-dias" value="">

            <!-- Guardar calendário (auto-save ao clicar, mantido como fallback) -->
            <div class="mt-3 flex justify-end no-print">
                <button id="btn-guardar-calendario" onclick="calDetalheGuardar()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-500 hover:bg-gray-50 flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Calendário
                </button>
            </div>
        </div>

        <!-- Legenda detalhada dos dias -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5" id="dias-detalhe">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900">Detalhe dos Dias</h3>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span id="det-pg-info">0 dias</span>
                    <select id="det-pg-size" class="border border-gray-300 rounded px-2 py-1 text-xs focus:ring-2 focus:ring-emerald-500 outline-none">
                        <option value="10">10</option>
                        <option value="15" selected>15</option>
                        <option value="30">30</option>
                        <option value="0">Todos</option>
                    </select>
                </div>
            </div>
            <div class="overflow-x-auto overflow-y-auto max-h-[420px] rounded border border-gray-100">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-white shadow-sm z-10">
                        <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                            <th class="pb-2 pt-1 pr-4">Data</th>
                            <th class="pb-2 pt-1 pr-4">Dia</th>
                            <th class="pb-2 pt-1 pr-4">Status</th>
                            <th class="pb-2 pt-1 pr-4">Entrada</th>
                            <th class="pb-2 pt-1 pr-4">Saída</th>
                            <th class="pb-2 pt-1 pr-4">Horas</th>
                            <th class="pb-2 pt-1 pr-4">Corte</th>
                        </tr>
                    </thead>
                    <tbody id="det-dias-tbody">
                        <?php $idx = 0; foreach ($todosDias as $diaStr):
                            $status = $diasStatus[$diaStr] ?? 'futuro';
                            $diaNum = (int) date('j', strtotime($diaStr));
                            $diaSemana = $diasNome[(int) date('w', strtotime($diaStr))];
                            $dataBr = date('d/m/Y', strtotime($diaStr));
                            $d = $diasPorData[$diaStr] ?? null;
                            $entrada = $d ? substr($d['entrada'] ?? '', 11, 5) : '-';
                            $saida = $d ? substr($d['saida'] ?? '', 11, 5) : '-';
                            $horasTrab = 0;
                            if ($d && !empty($d['entrada']) && !empty($d['saida'])) {
                                $horasTrab = round((strtotime($d['saida']) - strtotime($d['entrada'])) / 3600, 1);
                            }
                            $statusLabels = [
                                'folga' => ['Folga', 'text-purple-600 bg-purple-50'],
                                'futuro' => ['—', 'text-gray-400'],
                                'ferias' => ['Férias', 'text-blue-600 bg-blue-50'],
                                'falta' => ['Falta', 'text-red-600 bg-red-50'],
                                'presente' => ['Presente', 'text-emerald-600 bg-emerald-50'],
                                'presente_atraso' => ['Atraso', 'text-amber-600 bg-amber-50'],
                                'atraso' => ['Atraso', 'text-amber-600 bg-amber-50'],
                            ];
                            [$sLabel, $sClass] = $statusLabels[$status] ?? ['—', 'text-gray-400'];
                            $corte = '';
                            if ($status === 'falta') $corte = $fmt($stats['valor_dia']);
                            elseif (in_array($status, ['atraso', 'presente_atraso'], true)) $corte = $fmt($stats['valor_hora'] * 0.5) . ' (estim.)';
                        ?>
                        <tr class="border-b border-gray-50 hover:bg-gray-50/50" data-dia-idx="<?= $idx ?>">
                            <td class="py-2 pr-4 font-mono text-xs"><?= $dataBr ?></td>
                            <td class="py-2 pr-4"><?= $diaSemana ?></td>
                            <td class="py-2 pr-4"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $sClass ?>"><?= $sLabel ?></span></td>
                            <td class="py-2 pr-4 font-mono text-xs"><?= $entrada ?></td>
                            <td class="py-2 pr-4 font-mono text-xs"><?= $saida ?></td>
                            <td class="py-2 pr-4"><?= $horasTrab > 0 ? $horasTrab . 'h' : '-' ?></td>
                            <td class="py-2 pr-4 text-xs text-red-600"><?= $corte ?></td>
                        </tr>
                        <?php $idx++; endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between mt-3 text-xs text-gray-500" id="det-pg-controls">
                <div>
                    <button type="button" id="det-pg-prev" class="px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed" disabled><i class="fa-solid fa-chevron-left"></i> Anterior</button>
                    <button type="button" id="det-pg-next" class="px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed" disabled>Seguinte <i class="fa-solid fa-chevron-right"></i></button>
                </div>
                <span id="det-pg-label">Página 1 de 1</span>
            </div>
        </div>
    </div>
</div>

<!-- ========== IMPRESSOES DIGITAIS ========== -->
<div class="bg-white rounded-xl border border-gray-200 p-5 mt-6 no-print">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-fingerprint text-emerald-600"></i> Impressões digitais
            </h3>
            <p class="text-xs text-gray-500 mt-1">
                Até <strong>3 dedos</strong> por funcionário. Com um dedo registado, o ponto é marcado no terminal
                apenas encostando o dedo no leitor — sem PIN e sem foto.
            </p>
        </div>
        <button type="button" onclick="dgAbrir()" id="dg-btn-add"
                class="px-3 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-1.5 shadow-sm">
            <i class="fa-solid fa-plus"></i> Adicionar impressão digital
        </button>
    </div>

    <div id="dg-msg" class="hidden mb-3 text-sm rounded-lg px-3 py-2"></div>
    <div id="dg-lista" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="text-sm text-gray-400 col-span-3">A carregar…</div>
    </div>
</div>

<dialog id="modal-digital" class="rounded-xl p-0 shadow-2xl border-0 max-w-md w-full backdrop:bg-black/50">
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-fingerprint text-emerald-600"></i> Registar impressão digital
            </h3>
            <button type="button" onclick="dgFechar()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <p class="text-sm text-gray-500 mb-4">Funcionário: <strong><?= htmlspecialchars($func['nome']) ?></strong></p>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Posição</label>
                    <select id="dg-slot" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none"></select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Dedo</label>
                    <select id="dg-dedo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        <option>Polegar direito</option>
                        <option selected>Indicador direito</option>
                        <option>Médio direito</option>
                        <option>Polegar esquerdo</option>
                        <option>Indicador esquerdo</option>
                        <option>Médio esquerdo</option>
                    </select>
                </div>
            </div>

            <div class="rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50/60 p-5 text-center">
                <i class="fa-solid fa-fingerprint text-4xl text-emerald-500 mb-2" id="dg-icone"></i>
                <p id="dg-estado" class="text-sm text-emerald-800 font-medium">Coloque o dedo no leitor</p>
                <p class="text-xs text-gray-500 mt-1">O leitor envia a leitura automaticamente. Não é guardada nenhuma imagem do dedo.</p>
                <input type="text" id="dg-template" autocomplete="off"
                       class="mt-3 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-center tracking-wider focus:ring-2 focus:ring-emerald-500 outline-none"
                       placeholder="A aguardar leitura do leitor…">
                <button type="button" onclick="dgSensorDispositivo()" id="dg-btn-sensor"
                        class="mt-3 text-xs text-emerald-700 hover:text-emerald-800 underline">
                    Usar o sensor de impressão digital deste computador
                </button>
            </div>

            <div class="flex gap-2 pt-1">
                <button type="button" onclick="dgFechar()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
                <button type="button" onclick="dgGuardar()" id="dg-btn-save"
                        class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Guardar dedo</button>
            </div>
        </div>
    </div>
</dialog>

<!-- ========== AJUSTES DE DIA (justificacoes / horas extra) ========== -->
<div class="bg-white rounded-xl border border-gray-200 p-5 mt-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-sliders text-emerald-600"></i> Ajustes de dia
            </h3>
            <p class="text-xs text-gray-500 mt-1">
                Justifique faltas, marque folgas/licenças e lance horas extra. Estes ajustes são usados pelo motor de salários.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-xs text-gray-500">Mês</label>
            <input type="month" id="aj-mes" value="<?= htmlspecialchars($mesAtual ?? date('Y-m')) ?>"
                   onchange="carregarAjustes()"
                   class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
    </div>

    <form id="form-ajuste" class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4" onsubmit="guardarAjuste(event)">
        <input type="hidden" name="csrf" value="<?= Csrf::token() ?>">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Dia</label>
            <input type="date" name="dia" id="aj-dia" required
                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Tipo</label>
            <select name="tipo" id="aj-tipo" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="normal">Dia normal (só horas extra)</option>
                <option value="falta_justificada">Falta justificada</option>
                <option value="folga">Folga</option>
                <option value="licenca">Licença</option>
                <option value="ferias">Férias</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Vencimento</label>
            <select name="com_vencimento" id="aj-venc" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="1">Com vencimento (pago)</option>
                <option value="0">Sem vencimento (não pago)</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Horas extra</label>
            <input type="number" name="horas_extra" id="aj-extra" step="0.25" min="0" max="24" value="0"
                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div class="flex flex-col">
            <label class="block text-xs font-medium text-gray-700 mb-1">Observação</label>
            <div class="flex gap-2">
                <input type="text" name="observacao" id="aj-obs" maxlength="255" placeholder="Motivo…"
                       class="flex-1 min-w-0 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                <button type="submit" class="px-3 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700 transition">
                    <i class="fa-solid fa-check"></i>
                </button>
            </div>
        </div>
    </form>

    <div id="aj-msg" class="hidden mb-3 text-sm rounded-lg px-3 py-2"></div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-4 py-2 font-medium text-gray-500">Dia</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-500">Tipo</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-500">Vencimento</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-500">Horas extra</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-500">Observação</th>
                    <th class="w-10 px-4 py-2"><span class="sr-only">Acções</span></th>
                </tr>
            </thead>
            <tbody id="aj-lista" class="divide-y divide-gray-100">
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">A carregar…</td></tr>
            </tbody>
        </table>
    </div>
</div>



<dialog id="modal-ferias" class="rounded-xl p-0 shadow-2xl border-0 max-w-md w-full backdrop:bg-black/50">
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-umbrella-beach text-amber-500"></i> Adicionar férias
            </h3>
            <button type="button" onclick="document.getElementById('modal-ferias').close()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <p class="text-sm text-gray-500 mb-4">Funcionário: <strong><?= htmlspecialchars($func['nome']) ?></strong></p>
        <form id="form-ferias" class="space-y-4">
            <input type="hidden" name="csrf" value="<?= Csrf::token() ?>">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data inicial</label>
                    <input type="date" name="de" id="ferias-de" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data final</label>
                    <input type="date" name="ate" id="ferias-ate" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observação (opcional)</label>
                <input type="text" name="observacao" id="ferias-obs" maxlength="250" placeholder="ex.: Férias anuais 2026" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none">
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="incluir_fds" id="ferias-incluir-fds" value="1" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                Incluir fins-de-semana (sábado e domingo)
            </label>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
                <i class="fa-solid fa-circle-info mr-1"></i>
                Será criado um registo do tipo <strong>férias</strong> por cada dia no intervalo.
                Dias já marcados como férias são ignorados. Dias de férias <strong>não contam como falta</strong>
                no relatório salarial.
            </div>
            <div class="flex justify-end pt-2">
                <button type="submit" class="px-6 py-2 bg-amber-500 text-white rounded-lg font-medium hover:bg-amber-600">Adicionar férias</button>
            </div>
        </form>
    </div>
</dialog>

<style>
.cal-grid { max-width: 100%; }
.cal-header { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; margin-bottom: 3px; }
.cal-header-cell { text-align: center; font-size: 11px; font-weight: 600; color: #6b7280; padding: 6px 0; }
.cal-body { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
.cal-cell { text-align: center; padding: 8px 0; font-size: 13px; border-radius: 6px; cursor: pointer; color: #374151; transition: all .15s; min-height: 36px; display: flex; align-items: center; justify-content: center; }
.cal-cell:hover { opacity: 0.85; }
.cal-cell.cal-trabalho { background: #065f46; color: #fff; }
.cal-cell.cal-trabalho.cal-selected { background: #047857; color: #fff; font-weight: 700; box-shadow: inset 0 0 0 2px #a7f3d0; }
.cal-cell.cal-folga { background: #f3f4f6; color: #9ca3af; }
.cal-cell.cal-presente { background: #a7f3d0; border: 2px solid #065f46; color: #065f46; font-weight: 600; }
.cal-cell.cal-presente_atraso { background: linear-gradient(135deg, #a7f3d0 50%, #fde68a 50%); border: 2px solid #065f46; color: #065f46; font-weight: 600; }
.cal-cell.cal-falta { background: #fecaca; color: #991b1b; font-weight: 600; }
.cal-cell.cal-atraso { background: #fde68a; color: #92400e; font-weight: 600; }
.cal-cell.cal-ferias { background: #bfdbfe; color: #1e40af; font-weight: 600; }
.cal-cell.cal-futuro { color: #d1d5db; cursor: default; }
.cal-cell.cal-today { border: 2px solid #059669 !important; }
.cal-cell.cal-folga:hover, .cal-cell.cal-futuro:hover { opacity: 1; }
/* Modo folga: hover a roxo nos dias clicáveis */
.modo-folga .cal-cell.cal-selected.cal-futuro:hover,
.modo-folga .cal-cell.cal-selected.cal-trabalho:hover { background: #e9d5ff !important; }
@media print {
    .no-print { display: none !important; }
    body { font-size: 12px; }
    .cal-cell { font-size: 11px; padding: 4px 0; min-height: 24px; }
    #stats-mes { break-inside: avoid; }
    #dias-detalhe { break-inside: avoid; }
}
</style>
<script>
const detFuncId = <?= $func['id'] ?>;

// ===== Credenciais actuais (PIN / password) =====
(function () {
    const btn = document.getElementById('btn-ver-cred');
    if (!btn) return;
    let visivel = false;
    btn.addEventListener('click', async () => {
        const box = document.getElementById('cred-box');
        if (visivel) {
            box.classList.add('hidden');
            btn.innerHTML = '<i class="fa-solid fa-eye"></i> Mostrar';
            visivel = false;
            return;
        }
        btn.disabled = true;
        try {
            const r = await fetch(BASE_PATH + '/funcionarios/credenciais/' + detFuncId, {
                headers: { 'Accept': 'application/json' }, cache: 'no-store'
            }).then(r => r.json());
            if (!r.ok) { showToast(r.erro || 'Erro', 'error'); return; }
            document.getElementById('cred-pin').textContent  = r.pin || '(indisponível)';
            document.getElementById('cred-pass').textContent = r.password || '(indisponível)';
            const av = document.getElementById('cred-aviso');
            if (r.aviso) { av.textContent = r.aviso; av.classList.remove('hidden'); }
            else { av.classList.add('hidden'); }
            box.classList.remove('hidden');
            btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Ocultar';
            visivel = true;
        } finally {
            btn.disabled = false;
        }
    });
})();

function recalcularSalarioDet() {
    const base = parseFloat(document.getElementById('det-salario').value) || 0;
    const carga = parseFloat(document.getElementById('det-carga').value) || 8;
    const cDiaria = Math.max(1, carga);
    const salarioDia = base / 30;
    const salarioHora = salarioDia / cDiaria;
    const salarioMinuto = salarioHora / 60;
    document.getElementById('det-salario-dia').textContent = salarioDia.toFixed(2);
    document.getElementById('det-salario-hora').textContent = salarioHora.toFixed(2);
    document.getElementById('det-salario-minuto').textContent = salarioMinuto.toFixed(4);
}

function recalcularHorarioDet() {
    const entrada = document.getElementById('det-hora-entrada').value;
    const saida = document.getElementById('det-hora-saida').value;
    const cargaInput = document.getElementById('det-carga');
    function h2d(t) { if (!t) return null; const [h, m] = t.split(':'); return parseInt(h) + parseInt(m) / 60; }
    function d2h(d) { if (d == null) return ''; const h = Math.floor(d); const m = Math.round((d - h) * 60); return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0'); }
    const he = h2d(entrada), hs = h2d(saida), carga = h2d(cargaInput.value);
    if (he != null && carga != null && carga > 0) {
        document.getElementById('det-hora-saida').value = d2h(he + carga);
    } else if (hs != null && carga != null && carga > 0) {
        document.getElementById('det-hora-entrada').value = d2h(hs - carga);
    } else if (he != null && hs != null) {
        const diff = hs - he;
        if (diff > 0) cargaInput.value = diff.toFixed(1);
    }
}

// Auto-formata HH:MM ao digitar
function formatarHoraInput(el) {
    el.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 4);
        if (v.length >= 3) v = v.slice(0, 2) + ':' + v.slice(2);
        this.value = v;
    });
    el.addEventListener('blur', function () {
        if (/^\d{2}:\d{2}$/.test(this.value)) {
            const [h, m] = this.value.split(':').map(Number);
            if (h > 23) this.value = '23' + this.value.slice(2);
            if (m > 59) this.value = this.value.slice(0, 3) + '59';
        }
    });
}
formatarHoraInput(document.getElementById('det-hora-entrada'));
formatarHoraInput(document.getElementById('det-hora-saida'));

// Event listeners do formulário
['det-salario', 'det-carga'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', recalcularSalarioDet);
});
['det-hora-entrada', 'det-hora-saida', 'det-carga'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', recalcularHorarioDet);
});

// Guardar dados do funcionário
document.getElementById('form-detalhe').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const id = fd.get('id');
    fd.delete('id');
    const r = await fetch(BASE_PATH + '/funcionarios/atualizar/' + id, { method: 'POST', body: fd }).then(r => r.json());
    if (r.ok) {
        showToast('Dados atualizados com sucesso!');
        atualizarStats();
    } else {
        showToast(r.erro || 'Erro ao atualizar', 'error');
    }
});

// ===== Calendário =====
const DIAS_SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
let STATUS_MAP = <?= json_encode($diasStatus) ?>;
let detModo = 'dia'; // 'dia' ou 'folga'
let detSaveTimeout = null;

function calDetalheRender(ano, mes, diasSelecionados) {
    const grid = document.getElementById('det-cal-grid');
    if (!grid) return;
    const diasSet = new Set(diasSelecionados.map(Number));
    const primeiroDia = new Date(ano, mes - 1, 1);
    const ultimoDia = new Date(ano, mes, 0);
    const diasNoMes = ultimoDia.getDate();
    const inicioSemana = primeiroDia.getDay();

    let html = '<div class="cal-header">';
    for (let i = 0; i < 7; i++) {
        html += '<div class="cal-header-cell">' + DIAS_SEMANA[i] + '</div>';
    }
    html += '</div><div class="cal-body">';

    for (let i = 0; i < inicioSemana; i++) {
        html += '<div class="cal-cell cal-futuro"></div>';
    }

    for (let d = 1; d <= diasNoMes; d++) {
        const diaStr = ano + '-' + String(mes).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        const status = STATUS_MAP[diaStr] || 'futuro';
        const isSelected = diasSet.has(d) ? ' cal-selected' : '';
        const hoje = new Date();
        const isHoje = (ano === hoje.getFullYear() && mes === hoje.getMonth() + 1 && d === hoje.getDate());
        const hojeClass = isHoje ? ' cal-today' : '';

        let extraClass = '';
        if (status === 'folga') extraClass = ' cal-folga';
        else if (status === 'presente') extraClass = ' cal-presente';
        else if (status === 'presente_atraso') extraClass = ' cal-presente_atraso';
        else if (status === 'falta') extraClass = ' cal-falta';
        else if (status === 'atraso') extraClass = ' cal-atraso';
        else if (status === 'ferias') extraClass = ' cal-ferias';
        else if (status === 'futuro') extraClass = ' cal-futuro';
        else extraClass = ' cal-trabalho';

        // Determinar se este dia pode ser clicado conforme o modo atual
            const locked = (status === 'presente' || status === 'presente_atraso' || status === 'falta' || status === 'atraso' || status === 'ferias');
            let podeClicar = false;
            if (detModo === 'dia' && !locked) podeClicar = true;
            if (detModo === 'folga' && !locked && isSelected) podeClicar = true;
            if (detModo === 'folga' && status === 'futuro' && isSelected) podeClicar = true;

        const onClick = podeClicar ? `calDetalheToggle(${d})` : '';
        const cursorStyle = podeClicar ? ' cursor-pointer' : '';

        html += `<div class="cal-cell${extraClass}${isSelected}${hojeClass}${cursorStyle}" data-dia="${d}" onclick="${onClick}">${d}</div>`;
    }

    html += '</div>';
    grid.innerHTML = html;

    document.getElementById('det-dias-count').textContent = diasSelecionados.length;
    document.getElementById('det-dias-dias').value = JSON.stringify(diasSelecionados);
    document.getElementById('det-dias-mes').value = ano + '-' + String(mes).padStart(2, '0');
}

function calDetalheModo(modo) {
    detModo = modo;
    document.getElementById('modo-dia').className = modo === 'dia'
        ? 'px-3 py-1.5 font-medium bg-emerald-600 text-white'
        : 'px-3 py-1.5 font-medium text-gray-600 hover:bg-gray-50';
    document.getElementById('modo-folga').className = modo === 'folga'
        ? 'px-3 py-1.5 font-medium bg-purple-600 text-white'
        : 'px-3 py-1.5 font-medium text-gray-600 hover:bg-gray-50';
    document.getElementById('modo-label').textContent = modo === 'dia'
        ? 'adicionar como trabalho' : 'marcar como folga';
    // Re-renderizar com o modo atual (mantém os dias selecionados)
    const mesInput = document.getElementById('det-cal-mes');
    const [ano, mes] = (mesInput.value || '').split('-').map(Number);
    if (ano && mes) {
        const hidden = document.getElementById('det-dias-dias');
        const dias = JSON.parse(hidden.value || '[]');
        calDetalheRender(ano, mes, dias);
    }
}

function calDetalheToggle(dia) {
    const hidden = document.getElementById('det-dias-dias');
    if (!hidden) return;
    let dias = JSON.parse(hidden.value || '[]');
    const [ano, mes] = document.getElementById('det-cal-mes').value.split('-').map(Number);
    if (!ano || !mes) return;
    const diaStr = ano + '-' + String(mes).padStart(2, '0') + '-' + String(dia).padStart(2, '0');
    const status = STATUS_MAP[diaStr] || 'futuro';
    const locked = (status === 'presente' || status === 'presente_atraso' || status === 'falta' || status === 'atraso' || status === 'ferias');

    if (detModo === 'dia') {
        if (locked) return;
        const idx = dias.indexOf(dia);
        if (idx >= 0) {
            dias.splice(idx, 1);
            if (status === 'trabalho' || status === 'futuro') STATUS_MAP[diaStr] = 'folga';
        } else {
            dias.push(dia);
            STATUS_MAP[diaStr] = 'trabalho';
        }
        dias.sort((a, b) => a - b);
    } else {
        const idx = dias.indexOf(dia);
        if (idx < 0) return;
        dias.splice(idx, 1);
        dias.sort((a, b) => a - b);
        STATUS_MAP[diaStr] = 'folga';
    }
    calDetalheRender(ano, mes, dias);
    calDetalheAutoGuardar();
}

function calDetalheAutoGuardar() {
    if (detSaveTimeout) clearTimeout(detSaveTimeout);
    document.getElementById('det-saving-indicator').classList.remove('hidden');
    detSaveTimeout = setTimeout(() => {
        const mes = document.getElementById('det-dias-mes').value;
        const dias = document.getElementById('det-dias-dias').value;
        if (!mes) { document.getElementById('det-saving-indicator').classList.add('hidden'); return; }
        const fd = new FormData();
        fd.append('csrf', '<?= Csrf::token() ?>');
        fd.append('mes', mes);
        fd.append('dias', dias);
        fetch(BASE_PATH + '/funcionarios/dias-trabalho/' + detFuncId, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    atualizarStats();
                } else {
                    showToast(data.erro || 'Erro ao guardar', 'error');
                    document.getElementById('det-saving-indicator').classList.add('hidden');
                }
            })
            .catch(() => {
                showToast('Erro ao guardar calendário', 'error');
                document.getElementById('det-saving-indicator').classList.add('hidden');
            });
    }, 300);
}

function atualizarTabelaDetalhe(diasStatus, diasPorData, stats) {
    const tbody = document.getElementById('det-dias-tbody');
    if (!tbody) return;
    const diasNome = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
    const statusLabels = {
        folga:    ['Folga',    'text-purple-600 bg-purple-50'],
        futuro:   ['—',        'text-gray-400'],
        ferias:   ['Férias',   'text-blue-600 bg-blue-50'],
        falta:    ['Falta',    'text-red-600 bg-red-50'],
        presente: ['Presente', 'text-emerald-600 bg-emerald-50'],
        presente_atraso: ['Atraso', 'text-amber-600 bg-amber-50'],
        atraso:   ['Atraso',   'text-amber-600 bg-amber-50'],
    };
    const fmt = v => Number(v).toFixed(2).replace('.', ',');
    const mes = document.getElementById('det-cal-mes').value;
    const [_ano, _mes] = mes.split('-').map(Number);
    const ultimoDia = new Date(_ano, _mes, 0).getDate();
    let html = '';
    for (let d = 1; d <= ultimoDia; d++) {
        const diaStr = _ano + '-' + String(_mes).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        const status = diasStatus[diaStr] || 'futuro';
        const diaSemana = diasNome[new Date(_ano, _mes - 1, d).getDay()];
        const dataBr = String(d).padStart(2, '0') + '/' + String(_mes).padStart(2, '0') + '/' + _ano;
        const reg = diasPorData[diaStr] || null;
        const entrada = reg && reg.entrada ? reg.entrada.substring(11, 16) : '-';
        const saida = reg && reg.saida ? reg.saida.substring(11, 16) : '-';
        let horasTrab = '-';
        if (reg && reg.entrada && reg.saida) {
            const h = ((new Date(reg.saida) - new Date(reg.entrada)) / 3600).toFixed(1);
            horasTrab = h + 'h';
        }
        const [sLabel, sClass] = statusLabels[status] || ['—', 'text-gray-400'];
        let corte = '';
        if (status === 'falta') corte = fmt(stats.valor_dia);
        else if (status === 'atraso' || status === 'presente_atraso') corte = fmt(stats.valor_hora * 0.5) + ' (estim.)';
        html += '<tr class="border-b border-gray-50 hover:bg-gray-50/50">' +
            '<td class="py-2 pr-4 font-mono text-xs">' + dataBr + '</td>' +
            '<td class="py-2 pr-4">' + diaSemana + '</td>' +
            '<td class="py-2 pr-4"><span class="px-2 py-0.5 rounded-full text-xs font-medium ' + sClass + '">' + sLabel + '</span></td>' +
            '<td class="py-2 pr-4 font-mono text-xs">' + entrada + '</td>' +
            '<td class="py-2 pr-4 font-mono text-xs">' + saida + '</td>' +
            '<td class="py-2 pr-4">' + horasTrab + '</td>' +
            '<td class="py-2 pr-4 text-xs text-red-600">' + corte + '</td></tr>';
    }
    tbody.innerHTML = html;
    detPgPagina = 1;
    detPgAplicar();
}

function atualizarStats() {
    const mes = document.getElementById('det-dias-mes').value;
    fetch(BASE_PATH + '/funcionarios/stats/' + detFuncId + '?mes=' + mes)
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;
            const s = data.stats;
            const fmt = v => Number(v).toFixed(2).replace('.', ',');
            // Atualizar painel de estatísticas
            document.querySelector('#stats-mes h2').innerHTML = '<i class="fa-solid fa-chart-simple text-emerald-600"></i> Estatísticas — ' + mes;
            document.querySelector('#stats-mes .grid').innerHTML =
                '<div class="border border-gray-100 rounded-lg p-2.5"><p class="text-xs text-gray-500">Dias de trabalho</p><p class="text-lg font-bold text-gray-900">' + s.dias_esperados + '</p></div>' +
                '<div class="border border-gray-100 rounded-lg p-2.5"><p class="text-xs text-gray-500">Presenças</p><p class="text-lg font-bold text-emerald-600">' + s.presencas + '</p></div>' +
                '<div class="border border-gray-100 rounded-lg p-2.5"><p class="text-xs text-gray-500">Faltas</p><p class="text-lg font-bold ' + (s.faltas > 0 ? 'text-red-600' : 'text-gray-900') + '">' + s.faltas + '</p></div>' +
                '<div class="border border-gray-100 rounded-lg p-2.5"><p class="text-xs text-gray-500">Atrasos</p><p class="text-lg font-bold ' + (s.atrasos > 0 ? 'text-amber-600' : 'text-gray-900') + '">' + s.atrasos + ' <span class="text-xs text-gray-400">(' + s.horas_atraso + 'h)</span></p></div>' +
                '<div class="border border-gray-100 rounded-lg p-2.5"><p class="text-xs text-gray-500">Férias</p><p class="text-lg font-bold text-blue-600">' + s.dias_ferias + '</p></div>' +
                '<div class="border border-gray-100 rounded-lg p-2.5"><p class="text-xs text-gray-500">Folgas</p><p class="text-lg font-bold text-purple-600">' + s.dias_folga + '</p></div>' +
                '<div class="border border-gray-100 rounded-lg p-2.5"><p class="text-xs text-gray-500">Corte faltas</p><p class="text-sm font-bold text-red-600">' + fmt(s.corte_falta) + '</p></div>' +
                '<div class="border border-gray-100 rounded-lg p-2.5"><p class="text-xs text-gray-500">Corte atrasos</p><p class="text-sm font-bold text-amber-600">' + fmt(s.corte_atraso) + '</p></div>' +
                '<div class="col-span-2 border border-emerald-100 bg-emerald-50 rounded-lg p-2.5"><p class="text-xs text-emerald-600">Líquido a receber</p><p class="text-lg font-bold text-emerald-700">' + fmt(s.liquido) + ' <span class="text-xs font-normal">' + s.moeda + '</span></p></div>';

            // Atualizar STATUS_MAP com novos valores do servidor
            STATUS_MAP = data.diasStatus || STATUS_MAP;

            // Re-renderizar calendário com STATUS_MAP atualizado
            const _input = document.getElementById('det-cal-mes');
            if (_input) {
                const [_ano, _mes] = _input.value.split('-').map(Number);
                if (_ano && _mes) {
                    const _dias = JSON.parse(document.getElementById('det-dias-dias').value || '[]');
                    calDetalheRender(_ano, _mes, _dias);
                }
            }

            // Atualizar tabela Detalhe dos Dias
            atualizarTabelaDetalhe(data.diasStatus || {}, data.diasPorData || {}, s);

            document.getElementById('det-saving-indicator').classList.add('hidden');
        })
        .catch(() => {
            document.getElementById('det-saving-indicator').classList.add('hidden');
        });
}

function calDetalheSelecionarTodos() {
    const mesInput = document.getElementById('det-cal-mes');
    const [ano, mes] = (mesInput.value || '').split('-').map(Number);
    if (!ano || !mes) return;
    const ultimoDia = new Date(ano, mes, 0).getDate();
    const dias = Array.from({ length: ultimoDia }, (_, i) => i + 1);
    calDetalheRender(ano, mes, dias);
    calDetalheAutoGuardar();
}

function calDetalheLimparTodos() {
    const mesInput = document.getElementById('det-cal-mes');
    const [ano, mes] = (mesInput.value || '').split('-').map(Number);
    if (!ano || !mes) return;
    calDetalheRender(ano, mes, []);
    calDetalheAutoGuardar();
}

function calDetalheDiasSemana() {
    const mesInput = document.getElementById('det-cal-mes');
    const [ano, mes] = (mesInput.value || '').split('-').map(Number);
    if (!ano || !mes) return;
    const dias = [];
    const ultimoDia = new Date(ano, mes, 0).getDate();
    for (let d = 1; d <= ultimoDia; d++) {
        const diaSemana = new Date(ano, mes - 1, d).getDay();
        if (diaSemana >= 1 && diaSemana <= 5) dias.push(d);
    }
    calDetalheRender(ano, mes, dias);
    calDetalheAutoGuardar();
}

function calDetalheCarregar() {
    const mesInput = document.getElementById('det-cal-mes');
    if (!mesInput || !mesInput.value) return;
    const [ano, mes] = mesInput.value.split('-').map(Number);
    if (!ano || !mes) return;

    fetch(BASE_PATH + '/funcionarios/dias-trabalho/' + detFuncId + '/' + mesInput.value)
        .then(r => r.json())
        .then(data => {
            const dias = data.ok ? (data.dias || []) : [];
            calDetalheRender(ano, mes, dias);
        })
        .catch(() => calDetalheRender(ano, mes, []));
}

function calDetalheGuardar() {
    const mes = document.getElementById('det-dias-mes').value;
    const dias = document.getElementById('det-dias-dias').value;
    if (!mes) return;
    const fd = new FormData();
    fd.append('csrf', '<?= Csrf::token() ?>');
    fd.append('mes', mes);
    fd.append('dias', dias);
    fetch(BASE_PATH + '/funcionarios/dias-trabalho/' + detFuncId, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                showToast('Calendário guardado com sucesso!');
                atualizarStats();
            } else {
                showToast(data.erro || 'Erro ao guardar', 'error');
            }
        })
        .catch(() => showToast('Erro ao guardar calendário', 'error'));
}

function calDetalheMes(dir) {
    const input = document.getElementById('det-cal-mes');
    const [ano, mes] = input.value.split('-').map(Number);
    const d = new Date(ano, mes - 1 + dir, 1);
    input.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    carregarPaginaMes();
}

function calDetalheHoje() {
    const hoje = new Date();
    document.getElementById('det-cal-mes').value = hoje.getFullYear() + '-' + String(hoje.getMonth() + 1).padStart(2, '0');
    carregarPaginaMes();
}

function carregarPaginaMes() {
    const mes = document.getElementById('det-cal-mes').value;
    window.location.href = BASE_PATH + '/funcionario/' + detFuncId + '?mes=' + mes;
}

document.getElementById('det-cal-mes').addEventListener('change', carregarPaginaMes);

// ===== Paginação Detalhe dos Dias =====
let detPgPagina = 1;
function detPgAplicar() {
    const tbody = document.getElementById('det-dias-tbody');
    if (!tbody) return;
    const linhas = tbody.querySelectorAll('tr');
    const total = linhas.length;
    const tamanho = parseInt(document.getElementById('det-pg-size').value) || total;
    const totalPaginas = tamanho > 0 ? Math.ceil(total / tamanho) : 1;
    if (detPgPagina > totalPaginas) detPgPagina = totalPaginas;
    if (detPgPagina < 1) detPgPagina = 1;
    const inicio = (detPgPagina - 1) * tamanho;
    const fim = tamanho > 0 ? Math.min(inicio + tamanho, total) : total;
    linhas.forEach((tr, i) => { tr.style.display = (i >= inicio && i < fim) ? '' : 'none'; });
    document.getElementById('det-pg-prev').disabled = (detPgPagina <= 1);
    document.getElementById('det-pg-next').disabled = (detPgPagina >= totalPaginas);
    document.getElementById('det-pg-label').textContent = 'Página ' + detPgPagina + ' de ' + totalPaginas;
    document.getElementById('det-pg-info').textContent = total + ' dias';
}
document.getElementById('det-pg-prev').addEventListener('click', () => { detPgPagina--; detPgAplicar(); });
document.getElementById('det-pg-next').addEventListener('click', () => { detPgPagina++; detPgAplicar(); });
document.getElementById('det-pg-size').addEventListener('change', () => { detPgPagina = 1; detPgAplicar(); });

// ===== Férias =====
function abrirFeriasDetalhe() {
    document.getElementById('ferias-de').value = '';
    document.getElementById('ferias-ate').value = '';
    document.getElementById('ferias-obs').value = '';
    document.getElementById('ferias-incluir-fds').checked = false;
    document.getElementById('modal-ferias').showModal();
}

document.getElementById('form-ferias').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    await submeterFerias(fd);
});

async function submeterFerias(fd, forcar = false) {
    if (forcar) fd.set('forcar', '1');
    const resp = await fetch(BASE_PATH + '/funcionarios/ferias/' + detFuncId, { method: 'POST', body: fd });
    const r = await resp.json();
    if (r.ok) {
        showToast(r.mensagem || ('Inseridos ' + r.dias_inseridos + ' dia(s) de férias.'));
        document.getElementById('modal-ferias').close();
        atualizarStats();
        return;
    }
    if (resp.status === 409 && r.aviso && Array.isArray(r.conflitos)) {
        abrirConflito({
            titulo: 'Conflito com registos existentes',
            mensagem: r.mensagem || 'Há dias com marcações no intervalo escolhido.',
            conflitos: r.conflitos,
            onConfirmar: () => submeterFerias(fd, true),
        });
        return;
    }
    showToast(r.erro || 'Erro ao adicionar férias', 'error');
}

function abrirConflito({ titulo, mensagem, conflitos, onConfirmar }) {
    let m = document.getElementById('conflito-modal');
    if (!m) {
        m = document.createElement('div');
        m.id = 'conflito-modal';
        m.className = 'fixed inset-0 z-[125] hidden items-center justify-center bg-black/50 p-4 no-print';
        m.innerHTML = `
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 animate-fade-in max-h-[85vh] overflow-y-auto">
            <div class="flex items-start gap-3 mb-3">
                <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 id="conf-tit" class="text-lg font-bold text-gray-900"></h3>
                    <p id="conf-msg" class="text-sm text-gray-600 mt-1"></p>
                </div>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                <p class="text-xs font-medium text-amber-800 mb-2"><i class="fa-solid fa-calendar-day"></i> Dias com registos:</p>
                <ul id="conf-lista" class="text-xs text-amber-900 grid grid-cols-2 sm:grid-cols-3 gap-1 font-mono max-h-48 overflow-y-auto"></ul>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button id="conf-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancelar</button>
                <button id="conf-ok" class="px-4 py-2 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg flex items-center gap-2">
                    <i class="fa-solid fa-check"></i> Avançar mesmo assim
                </button>
            </div>
        </div>`;
        document.body.appendChild(m);
    }
    m.querySelector('#conf-tit').textContent = titulo;
    m.querySelector('#conf-msg').textContent = mensagem;
    const ul = m.querySelector('#conf-lista');
    ul.innerHTML = conflitos.map(d => `<li class="px-2 py-1 bg-white rounded border border-amber-200">${d}</li>`).join('');
    m.classList.remove('hidden'); m.classList.add('flex');
    const close = () => { m.classList.add('hidden'); m.classList.remove('flex'); };
    m.querySelector('#conf-cancel').onclick = close;
    m.querySelector('#conf-ok').onclick = async () => { close(); await onConfirmar(); };
}

// ===== Imprimir =====
function imprimirMes() {
    document.getElementById('dias-detalhe').classList.remove('hidden');
    window.print();
}

// ===== Ajustes de dia =====
const AJ_CSRF = '<?= Csrf::token() ?>';

function ajMsg(texto, ok) {
    const el = document.getElementById('aj-msg');
    el.textContent = texto;
    el.className = 'mb-3 text-sm rounded-lg px-3 py-2 ' +
        (ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700');
    setTimeout(() => el.classList.add('hidden'), 4000);
}

function ajRender(ajustes) {
    const tb = document.getElementById('aj-lista');
    if (!ajustes || !ajustes.length) {
        tb.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Sem ajustes neste mês.</td></tr>';
        return;
    }
    tb.innerHTML = ajustes.map(a => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2 text-gray-900">${a.dia.split('-').reverse().join('/')}</td>
            <td class="px-4 py-2 text-gray-700">${a.tipo_label}</td>
            <td class="px-4 py-2">${a.com_vencimento
                ? '<span class="text-emerald-700">Pago</span>'
                : '<span class="text-red-600">Não pago</span>'}</td>
            <td class="px-4 py-2 text-gray-700">${Number(a.horas_extra).toFixed(2)} h</td>
            <td class="px-4 py-2 text-gray-500">${a.observacao ? a.observacao.replace(/[<>]/g, '') : '—'}</td>
            <td class="px-4 py-2 text-right">
                <button type="button" onclick="removerAjuste('${a.dia}')" title="Remover ajuste"
                        class="w-7 h-7 rounded-lg border border-gray-200 text-gray-400 hover:bg-red-50 hover:text-red-600 transition">
                    <i class="fa-solid fa-trash text-xs"></i>
                </button>
            </td>
        </tr>`).join('');
}

function carregarAjustes() {
    const mes = document.getElementById('aj-mes').value;
    if (!mes) { return; }
    fetch(BASE_PATH + '/funcionarios/ajustes/' + detFuncId + '?mes=' + mes)
        .then(r => r.json())
        .then(d => { if (d.ok) { ajRender(d.ajustes); } else { ajMsg(d.erro || 'Erro ao carregar.', false); } })
        .catch(() => ajMsg('Falha de comunicação.', false));
}

function guardarAjuste(ev) {
    ev.preventDefault();
    const fd = new FormData(document.getElementById('form-ajuste'));
    fetch(BASE_PATH + '/funcionarios/ajustes/' + detFuncId, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { ajMsg(d.erro || 'Não foi possível guardar.', false); return; }
            ajRender(d.ajustes);
            document.getElementById('aj-obs').value = '';
            document.getElementById('aj-extra').value = '0';
            ajMsg('Ajuste guardado.', true);
        })
        .catch(() => ajMsg('Falha de comunicação.', false));
}

function removerAjuste(dia) {
    if (!confirm('Remover o ajuste do dia ' + dia.split('-').reverse().join('/') + '?')) { return; }
    const fd = new FormData();
    fd.append('csrf', AJ_CSRF);
    fd.append('dia', dia);
    fetch(BASE_PATH + '/funcionarios/ajustes/remover/' + detFuncId, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { ajMsg(d.erro || 'Não foi possível remover.', false); return; }
            if (d.ajustes) { ajRender(d.ajustes); } else { carregarAjustes(); }
            ajMsg('Ajuste removido.', true);
        })
        .catch(() => ajMsg('Falha de comunicação.', false));
}

document.getElementById('aj-tipo').addEventListener('change', function () {
    const padrao = { falta_justificada: '1', folga: '0', licenca: '0', ferias: '1', normal: '1' };
    document.getElementById('aj-venc').value = padrao[this.value] ?? '1';
});

// Inicializar
recalcularSalarioDet();
recalcularHorarioDet();
calDetalheCarregar();
detPgAplicar();
carregarAjustes();

// ===== Impressoes digitais =====
let dgOrigem = 'leitor';
let dgSlotLivre = 1;

function dgMsg(texto, ok) {
    const el = document.getElementById('dg-msg');
    el.textContent = texto;
    el.className = 'mb-3 text-sm rounded-lg px-3 py-2 ' + (ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700');
    setTimeout(() => el.classList.add('hidden'), 5000);
}

function dgRender(d) {
    dgSlotLivre = d.slotLivre;
    const max = d.max || 3;
    const porSlot = {};
    (d.digitais || []).forEach(x => porSlot[Number(x.slot)] = x);
    let html = '';
    for (let i = 1; i <= max; i++) {
        const x = porSlot[i];
        if (x) {
            html += `
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs text-emerald-700 font-medium">Dedo ${i}</p>
                        <p class="text-sm font-semibold text-gray-900 mt-0.5">${x.dedo}</p>
                        <p class="text-xs text-gray-500 mt-1">Registado em ${x.criado_em.replace('T',' ').slice(0,16)}</p>
                        <p class="text-xs text-gray-500">Último uso: ${x.ultimo_uso ? x.ultimo_uso.replace('T',' ').slice(0,16) : 'nunca'}</p>
                    </div>
                    <button type="button" onclick="dgRemover(${x.id})" title="Remover"
                            class="w-7 h-7 rounded-lg border border-gray-200 bg-white text-gray-400 hover:bg-red-50 hover:text-red-600 transition">
                        <i class="fa-solid fa-trash text-xs"></i>
                    </button>
                </div>
            </div>`;
        } else {
            html += `
            <button type="button" onclick="dgAbrir(${i})"
                    class="rounded-xl border-2 border-dashed border-gray-200 p-4 text-center text-gray-400 hover:border-emerald-300 hover:text-emerald-600 transition">
                <i class="fa-solid fa-fingerprint text-2xl mb-1"></i>
                <p class="text-xs">Dedo ${i} livre — adicionar</p>
            </button>`;
        }
    }
    document.getElementById('dg-lista').innerHTML = html;
    const btn = document.getElementById('dg-btn-add');
    btn.disabled = !dgSlotLivre;
    btn.classList.toggle('opacity-50', !dgSlotLivre);
    btn.classList.toggle('cursor-not-allowed', !dgSlotLivre);
}

function dgCarregar() {
    fetch(BASE_PATH + '/funcionarios/digitais/' + detFuncId, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(d => { if (d.ok) { dgRender(d); } })
        .catch(() => {});
}

function dgAbrir(slot) {
    const sel = document.getElementById('dg-slot');
    sel.innerHTML = '';
    for (let i = 1; i <= 3; i++) {
        const o = document.createElement('option');
        o.value = i; o.textContent = 'Dedo ' + i;
        sel.appendChild(o);
    }
    sel.value = slot || dgSlotLivre || 1;
    dgOrigem = 'leitor';
    document.getElementById('dg-template').value = '';
    document.getElementById('dg-estado').textContent = 'Coloque o dedo no leitor';
    document.getElementById('modal-digital').showModal();
    setTimeout(() => document.getElementById('dg-template').focus(), 80);
}

function dgFechar() { document.getElementById('modal-digital').close(); }

// Leitores USB em modo teclado escrevem a leitura e terminam com Enter.
document.addEventListener('DOMContentLoaded', function () {
    const inp = document.getElementById('dg-template');
    if (!inp) { return; }
    inp.addEventListener('input', function () {
        dgOrigem = 'leitor';
        document.getElementById('dg-estado').textContent = this.value.length >= 8
            ? 'Leitura recebida — pode guardar' : 'A ler…';
    });
    inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); dgGuardar(); }
    });
});

async function dgSensorDispositivo() {
    if (!window.PublicKeyCredential || !navigator.credentials) {
        dgMsg('Este computador não tem sensor de impressão digital compatível. Use um leitor USB.', false);
        return;
    }
    try {
        const desafio = new Uint8Array(32);
        crypto.getRandomValues(desafio);
        const cred = await navigator.credentials.create({
            publicKey: {
                challenge: desafio,
                rp: { name: 'FarmaPonto' },
                user: {
                    id: new TextEncoder().encode('func-' + detFuncId + '-' + document.getElementById('dg-slot').value),
                    name: <?= json_encode($func['codigo']) ?>,
                    displayName: <?= json_encode($func['nome']) ?>
                },
                pubKeyCredParams: [{ type: 'public-key', alg: -7 }, { type: 'public-key', alg: -257 }],
                authenticatorSelection: { authenticatorAttachment: 'platform', residentKey: 'required', userVerification: 'required' },
                timeout: 60000,
                attestation: 'none'
            }
        });
        const id = btoa(String.fromCharCode(...new Uint8Array(cred.rawId))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        dgOrigem = 'dispositivo';
        document.getElementById('dg-template').value = id;
        document.getElementById('dg-estado').textContent = 'Dedo lido pelo sensor deste computador';
    } catch (e) {
        dgMsg('Não foi possível ler o dedo neste computador: ' + (e.message || e), false);
    }
}

function dgGuardar() {
    const template = document.getElementById('dg-template').value.trim();
    if (template.length < 8) { dgMsg('Ainda não há leitura válida do dedo.', false); return; }
    const fd = new FormData();
    fd.append('csrf', AJ_CSRF);
    fd.append('slot', document.getElementById('dg-slot').value);
    fd.append('dedo', document.getElementById('dg-dedo').value);
    fd.append('origem', dgOrigem);
    fd.append('template', template);
    const btn = document.getElementById('dg-btn-save');
    btn.disabled = true;
    fetch(BASE_PATH + '/funcionarios/digitais/' + detFuncId, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            if (!d.ok) { dgMsg(d.erro || 'Não foi possível guardar.', false); return; }
            dgRender(d);
            dgFechar();
            dgMsg('Impressão digital registada. O funcionário já pode marcar ponto com este dedo.', true);
        })
        .catch(() => { btn.disabled = false; dgMsg('Falha de comunicação.', false); });
}

function dgRemover(id) {
    if (!confirm('Remover esta impressão digital?')) { return; }
    const fd = new FormData();
    fd.append('csrf', AJ_CSRF);
    fetch(BASE_PATH + '/funcionarios/digitais/remover/' + id, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { dgMsg(d.erro || 'Não foi possível remover.', false); return; }
            dgRender(d);
            dgMsg('Impressão digital removida.', true);
        })
        .catch(() => dgMsg('Falha de comunicação.', false));
}

dgCarregar();

</script>

