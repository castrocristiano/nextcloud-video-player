# Design

## Context

Veja `proposal.md` para a motivação e o escopo geral. Atualmente:
- As preferências do usuário (`playback_rate`, `autoplay_next`, `sort_by`, `volume`, `muted`, `preferred_subtitle`) são persistidas na tabela `ncvp_user_preferences` via `PreferenceService` e `PreferenceController` (`GET`/`PUT /api/v1/preferences`), com cache otimista em `localStorage` (`ncvp:prefs`).
- O progresso de reprodução por vídeo é salvo na tabela `ncvp_playback_progress` com `updated_at`, `position_seconds`, `duration_seconds` e `completed`.
- O player renderiza legendas WebVTT convertidas em memória por meio de um overlay dedicado `#subtitleOverlay` (`.ncvp-subtitle-line` em `css/style.css`) com tamanho de fonte atualmente fixo (`clamp(16px, 2.1vw, 26px)`).

## Goals / Non-Goals

**Goals:**
- Estender `ncvp_user_preferences` de forma retrocompatível com Nextcloud migrations (`Version0002Date...`) para persistir `last_root_id`, `last_folder_path` e `subtitle_scale`.
- Restaurar a última pasta visitada no boot do frontend com fallback seguro e sem gerar chamadas redundantes ou loops de erro quando uma pasta tiver sido movida/removida.
- Expor o último vídeo assistido não concluído no payload de `GET /api/v1/roots` (ou endpoint dedicado leve) para renderizar um atalho "Continuar assistindo" no catálogo.
- Controlar o tamanho da fonte das legendas por meio de uma variável CSS `--ncvp-subtitle-scale` aplicada ao `#subtitleOverlay` em modo janela e tela cheia.

**Non-Goals:**
- Armazenar histórico de navegação completo (pilha de pastas) no servidor; apenas o último local válido visitado é mantido.
- Alterar tabelas ou gravar qualquer arquivo dentro das pastas de mídia do Nextcloud.

## Decisions

1. **Persistência da última pasta em `ncvp_user_preferences` vs. apenas `localStorage`**:
   - *Decisão*: Persistir `last_root_id` e `last_folder_path` em `ncvp_user_preferences` (com cache local em `localStorage`), atualizando via `PUT /api/v1/preferences` de forma debounced/assíncrona ao navegar entre pastas ou trocar de raiz.
   - *Alternativa considerada*: Usar apenas `localStorage`. Rejeitada porque o usuário perde o estado ao trocar de dispositivo (ex.: desktop → celular/TV).

2. **Descoberta do último vídeo assistido a partir de `ncvp_playback_progress`**:
   - *Decisão*: Reaproveitar a tabela existente `ncvp_playback_progress` consultando o registro mais recente (`ORDER BY updated_at DESC`) com `completed = 0` e `position_seconds > 3`, validando que o `root_key` pertence a `FolderConfigService::getEffectiveRoots($userId)` e que o arquivo ainda existe via `SafeNodeResolver`.
   - *Alternativa considerada*: Criar colunas `last_video_*` em `ncvp_user_preferences`. Rejeitada para evitar duplicação de verdade entre progresso e preferências, já que `ncvp_playback_progress` já registra cada heartbeat e encerramento de sessão.

3. **Controle de tamanho da legenda via CSS Custom Property (`--ncvp-subtitle-scale`)**:
   - *Decisão*: Definir `--ncvp-subtitle-scale: 1` em `#playerModal` / `.ncvp-subtitle-overlay` e calcular `font-size: calc(clamp(16px, 2.1vw, 26px) * var(--ncvp-subtitle-scale, 1))` em `.ncvp-subtitle-line`, variando entre `0.75` (`75%`) e `2.0` (`200%`) em passos de `0.15` ou `0.25` (`A-` / `A+`).
   - *Alternativa considerada*: Alterar `style.fontSize` em pixels diretamente em cada `span` criado a cada fala. Rejeitada porque o CSS custom property no container preserva a responsividade automática entre janela e tela cheia (`vw` + escala do usuário).

## Risks / Trade-offs

- **[Risco] Pasta salva no banco foi excluída ou renomeada no SFTP/Nextcloud** → *Mitigação*: No boot do frontend (`loadRoots()`), validar se `lastRootId` existe em `state.roots`; caso `/api/v1/catalog` retorne `403`/`404` para `lastFolderPath`, limpar `lastFolderPath` para `"/"` e recarregar a raiz sem exibir erro bloqueante.
- **[Risco] Verificação de existência do último vídeo no SFTP atrasar o `/api/v1/roots`** → *Mitigação*: Se o nó no Nextcloud lançar exceção ou não existir, capturar o erro silenciosamente e retornar `lastWatched: null` sem bloquear o carregamento das raízes.

## Migration Plan

1. Criar `lib/Migration/Version0002Date20261122000000.php` verificando `hasColumn()` antes de adicionar `last_root_id` (`string`, length 32, nullable), `last_folder_path` (`string`, length 1024, nullable) e `subtitle_scale` (`float`, default `1.0`, notnull `true`) em `ncvp_user_preferences`.
2. Atualizar `UserPreferenceEntity`, `PreferenceService` e `ProgressService`/`ConfigController`.
3. Atualizar `templates/main.php`, `css/style.css`, `js/modules/*`, `js/main.js` e `l10n/pt_BR.*`.
4. Executar PHPStan, PHPUnit, ESLint, Vitest e `openspec validate --all --strict --no-interactive`.
