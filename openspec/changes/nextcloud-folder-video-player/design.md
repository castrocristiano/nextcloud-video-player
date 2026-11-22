# Design

## Context

Este design detalha a arquitetura técnica do aplicativo Nextcloud **`nextcloud_video_player`** (compatível com **Nextcloud 30–32**, **PHP 8.2+**, frontend **Vanilla JS + CSS**), portando as funcionalidades comprovadas do projeto [lan-video-player](https://github.com/castrocristiano/lan-video-player) para o ecossistema Nextcloud com **persistência relacional em tabelas**, **controle formal de sessão** e **publicação oficial no catálogo da Nextcloud App Store (`apps.nextcloud.com`)**. Veja `proposal.md` para motivação e escopo, e `specs/*/spec.md` para os contratos comportamentais.

Restrições fundamentais do ambiente alvo:
- Nextcloud 30–32 com PHP 8.2+ (`declare(strict_types=1);`), injeção de dependência via `OCP\AppFramework\Bootstrap\IBootstrap`, migrações via `OCP\Migration\SimpleMigrationStep`, e proteção CSRF + CSP rigorosa (`default-src 'self'`).
- Acesso a arquivos exclusivamente pela camada virtual `OCP\Files\IRootFolder` para herdar permissões nativas, compartilhamentos e montagens de *External Storage*.
- Compatibilidade total de banco de dados entre PostgreSQL, MariaDB/MySQL (`utf8mb4`) e SQLite.
- Qualidade validada pela esteira CI/CD no padrão `contas` (validação OpenSpec, XSD de `info.xml`, PHPUnit + Clover, PHPStan, ESLint, Vitest + LCOV, SonarQube Community Quality Gate, assinatura de código e publicação na App Store).

## Goals / Non-Goals

**Goals:**
- Entregar um app Nextcloud nativo, leve e pronto para o catálogo da Nextcloud App Store (`multimedia`), com código PHP tipado testável via PHPUnit e frontend Vanilla JS modular testável via Vitest.
- Persistir pastas configuradas, progresso de reprodução, preferências de usuário e sessões ativas em tabelas relacionais dedicadas do Nextcloud, sincronizando a experiência entre múltiplos dispositivos da mesma conta.
- Controlar explicitamente sessões de reprodução (*heartbeat*, expiração, revogação no logout, limite configurável de sessões simultâneas e liberação do *lock* de sessão PHP via `OCP\ISession::close()`).
- Garantir isolamento rigoroso de acesso (somente pastas configuradas e acessíveis pelo usuário autenticado) e operação estritamente somente-leitura.
- Atingir streaming fluido com `HTTP 206 Partial Content` (Range) sobre fluxos da VFS do Nextcloud (`$file->fopen('r')`) e fallback opcional em memória via `ffmpeg`.

**Non-Goals:**
- Não implementar escrita em disco nas pastas de vídeo (nem sidecars `.vtt`, nem cache de miniaturas dentro das pastas de mídia).
- Não usar `AppAPI` / microserviço externo na v1 (mantendo instalação simples via Nextcloud App Store ou tarball em `apps/nextcloud_video_player`).
- Não versionar chaves privadas de assinatura ou tokens da App Store no repositório Git.

## Decisions

### 1. Identificador e Estrutura do App Nextcloud
- **Decisão**: Usar `appId` = `nextcloud_video_player` e namespace PHP `OCA\NextcloudVideoPlayer`.
- **Justificativa**: O schema oficial do Nextcloud (`info.xsd`) exige `[a-z0-9_]` sem hífens para `<id>` e diretório em `apps/`. Alinha-se ao nome do repositório (`nextcloud-video-player`).
- **Estrutura de Diretórios**:
  ```text
  appinfo/
    info.xml                 # Metadados Nextcloud App Store (validado contra info.xsd)
    routes.php               # Rotas Web (page) e API REST JSON/Stream
    signature.json           # Gerado exclusivamente no pipeline de release (code signing)
  lib/
    AppInfo/
      Application.php        # IBootstrap: registra navegação, DI, Admin Settings e listener de logout
    Migration/
      Version0001Date20261121000000.php  # Cria tabelas ncvp_* via OCP\DB\ISchemaWrapper
    Db/
      FolderEntity.php / FolderMapper.php
      PlaybackProgressEntity.php / PlaybackProgressMapper.php
      UserPreferenceEntity.php / UserPreferenceMapper.php
      PlaybackSessionEntity.php / PlaybackSessionMapper.php
    BackgroundJob/
      SessionCleanupJob.php  # TimedJob que expira sessões ociosas e limpa registros antigos
    Listener/
      UserLoggedOutListener.php  # Revoga sessões ativas do usuário ao sair do Nextcloud
    Settings/
      AdminSection.php       # Seção no painel de Administração do Nextcloud
      AdminSettings.php      # ISettings para pastas globais, FFmpeg e política de sessões
    Controller/
      PageController.php     # Renderiza template principal com CSP estrita
      ConfigController.php   # CRUD de pastas pessoais + listagem de raízes híbridas + picker
      SettingsController.php # Configuração global de pastas, FFmpeg e sessões (Admin only)
      CatalogController.php  # Listagem de pasta, breadcrumbs, busca global e legendas
      StreamController.php   # Streaming HTTP Range (com session close), probe/transcode e thumb
      SessionController.php  # Criação, heartbeat, listagem e encerramento/revogação de sessões
      PreferenceController.php # Leitura/atualização de preferências e consulta de progresso em lote
    Service/
      FolderConfigService.php   # Mescla raízes globais + pessoais via FolderMapper e IRootFolder
      SafeNodeResolver.php      # Normalização, anti-traversal e checagem de raiz autorizada
      VideoCatalogService.php   # Filtragem de vídeos, ordenação, busca recursiva e status de progresso
      SubtitleService.php       # Descoberta de .vtt/.srt irmãos e conversão SRT -> WebVTT em memória
      MediaStreamService.php    # Parsing/validação HTTP Range e leitura em chunks
      FfmpegService.php         # Probe (ffprobe), transcode MP4 fragmentado (stdout) e thumbs em temp dir
      PlaybackSessionService.php # Ciclo de vida de sessão, validação de token, limite simultâneo e expiração
      ProgressService.php       # Persistência de progresso e regra de conclusão (>= 95%)
      PreferenceService.php     # Persistência e defaults de preferências por usuário
  templates/
    main.php                 # Shell do catálogo, player e painel de sessões (sem inline JS/CSS)
    admin.php                # Interface de configuração global de pastas, FFmpeg e sessões
  js/
    main.js                  # Entrypoint do app carregado via Util::addScript
    admin.js                 # Entrypoint das configurações de administração
    modules/
      api.js                 # Cliente HTTP com OC.requestToken (CSRF) e OC.generateUrl
      state.js               # Estado em memória sincronizado com /api/v1/preferences e /api/v1/progress
      session.js             # Ciclo de vida da sessão no cliente (start, heartbeat, keepalive/sendBeacon)
      catalog.js             # Renderização de raízes, breadcrumbs, pastas, vídeos, busca e ordenação
      picker.js              # Modal de gerenciamento e seletor visual de pastas do Nextcloud
      player.js              # Motor HTML5, seek Range/FFmpeg, fullscreen estilo YouTube, atalhos, legendas e autoplay
      formatters.js          # Funções puras (bytes, datas, duração)
  css/
    style.css                # Layout responsivo usando var(--color-*) do Nextcloud
  img/
    app.svg                  # Ícone do app (SVG local)
  l10n/                      # Arquivos de tradução (pt_BR, en) para o catálogo Nextcloud
  screenshots/               # Capturas de tela (claro/escuro) referenciadas no info.xml para a App Store
  CHANGELOG.md               # Histórico de versões exigido/recomendado na App Store
  tests/
    Unit/                    # Testes PHPUnit (Migrations, Db Mappers, Services, Controllers, Jobs, Listeners)
    js/                      # Testes Vitest (formatters, state, session, catalog, player)
  ```

### 2. Modelo Relacional em Tabelas Dedicadas (`OCP\Migration\SimpleMigrationStep` + `QBMapper`)
- **Decisão**: Criar 4 tabelas dedicadas com prefixo curto `ncvp_` (respeitando o limite de 27 caracteres sem `oc_` imposto pelo validador de schema do Nextcloud), usando `path_hash` (`SHA-256`, `varchar(64)`) nos índices únicos para evitar estouro de chave em MariaDB/MySQL `utf8mb4` com caminhos longos.
- **Alternativa considerada**: Guardar JSON em `OCP\IConfig` e progresso apenas em `localStorage`. Descartada por impedir sincronização multi-dispositivo, limitar consultas/auditoria de sessões ativas e dificultar controle de concorrência.
- **`OCP\IConfig` (escopo residual operacional)**: Mantém apenas chaves de infraestrutura do Admin: `ffmpeg_enabled` (`'0'`/`'1'`), `ffmpeg_path` (`'ffmpeg'`), `ffprobe_path` (`'ffprobe'`), `session_ttl_seconds` (`180`), `max_concurrent_sessions` (`0` = ilimitado).

#### Esquema das Tabelas (`Version0001Date20261121000000.php`)

1. **`ncvp_folders`** — Pastas configuradas (globais do Admin e pessoais de cada usuário):
   | Coluna | Tipo (`ISchemaWrapper`) | Restrições / Finalidade |
   | :--- | :--- | :--- |
   | `id` | `BIGINT` (PK, autoincrement) | Chave primária |
   | `root_key` | `STRING(64)` | Único — identificador público estável da raiz |
   | `scope` | `STRING(16)` | `'global'` ou `'user'` |
   | `user_id` | `STRING(64)` | `''` para `'global'`; `uid` Nextcloud para `'user'` (evita NULL em unique index no SQLite/MySQL) |
   | `folder_path` | `STRING(1024)` | Caminho normalizado no Nextcloud (ex.: `/Filmes`) |
   | `path_hash` | `STRING(64)` | `hash('sha256', folder_path)` |
   | `label` | `STRING(255)` | Rótulo amigável exibido na UI |
   | `created_by` | `STRING(64)` | `uid` do criador (admin ou próprio usuário) |
   | `created_at` | `BIGINT` | Timestamp Unix UTC |
   | `updated_at` | `BIGINT` | Timestamp Unix UTC |
   - **Índices**: `UNIQUE(root_key)`, `UNIQUE(scope, user_id, path_hash)`, `INDEX(scope, user_id)`.

2. **`ncvp_playback_progress`** — Progresso de reprodução por usuário (sincronizado entre dispositivos):
   | Coluna | Tipo | Restrições / Finalidade |
   | :--- | :--- | :--- |
   | `id` | `BIGINT` (PK, autoincrement) | Chave primária |
   | `user_id` | `STRING(64)` | `uid` Nextcloud proprietário |
   | `file_id` | `BIGINT` (nullable) | `Node::getId()` do Nextcloud |
   | `root_key` | `STRING(64)` | Referência lógica à raiz configurada |
   | `relative_path` | `STRING(1024)` | Caminho relativo seguro dentro da raiz |
   | `path_hash` | `STRING(64)` | `hash('sha256', relative_path)` |
   | `position_seconds` | `FLOAT` | Último segundo reproduzido (`0` quando concluído) |
   | `duration_seconds` | `FLOAT` | Duração total conhecida |
   | `progress_ratio` | `FLOAT` | `0.0` a `1.0` |
   | `completed` | `BOOLEAN` | `true` quando `ratio >= 0.95` |
   | `last_session_token` | `STRING(64)` (nullable) | Última sessão que atualizou o progresso |
   | `updated_at` | `BIGINT` | Timestamp Unix UTC |
   - **Índices**: `UNIQUE(user_id, root_key, path_hash)`, `INDEX(user_id, updated_at)`.

3. **`ncvp_user_preferences`** — Preferências do player por usuário:
   | Coluna | Tipo | Restrições / Finalidade |
   | :--- | :--- | :--- |
   | `id` | `BIGINT` (PK, autoincrement) | Chave primária |
   | `user_id` | `STRING(64)` | Único (`uid` Nextcloud) |
   | `playback_rate` | `FLOAT` | Padrão `1.0` |
   | `autoplay_next` | `BOOLEAN` | Padrão `true` |
   | `sort_by` | `STRING(32)` | Padrão `'name_asc'` |
   | `volume` | `FLOAT` | Padrão `1.0` (`0.0`–`1.0`) |
   | `muted` | `BOOLEAN` | Padrão `false` |
   | `preferred_subtitle` | `STRING(32)` (nullable) | Código de idioma preferido (ex.: `pt-BR`) |
   | `updated_at` | `BIGINT` | Timestamp Unix UTC |
   - **Índices**: `UNIQUE(user_id)`.

4. **`ncvp_playback_sessions`** — Controle de sessões de reprodução e streaming:
   | Coluna | Tipo | Restrições / Finalidade |
   | :--- | :--- | :--- |
   | `id` | `BIGINT` (PK, autoincrement) | Chave primária |
   | `session_token` | `STRING(64)` | Único (`bin2hex(random_bytes(32))`) |
   | `user_id` | `STRING(64)` | `uid` Nextcloud proprietário |
   | `root_key` | `STRING(64)` | Raiz autorizada |
   | `relative_path` | `STRING(1024)` | Vídeo reproduzido |
   | `stream_mode` | `STRING(16)` | `'direct'` ou `'transcode'` |
   | `client_agent` | `STRING(255)` (nullable) | User-Agent truncado para identificação do dispositivo |
   | `status` | `STRING(16)` | `'active'`, `'paused'`, `'ended'`, `'expired'`, `'revoked'` |
   | `started_at` | `BIGINT` | Timestamp Unix UTC |
   | `last_heartbeat_at` | `BIGINT` | Última confirmação de atividade |
   | `expires_at` | `BIGINT` | `last_heartbeat_at + session_ttl_seconds` |
   | `ended_at` | `BIGINT` (nullable) | Timestamp de encerramento/revogação |
   - **Índices**: `UNIQUE(session_token)`, `INDEX(user_id, status, expires_at)`, `INDEX(status, expires_at)`.

### 3. Controle de Sessão, Concorrência e Liberação de Lock PHP
- **Autenticação & CSRF**: Todas as rotas ficam sob `/apps/nextcloud_video_player/` sem `@PublicPage` (exigindo `OCP\IUserSession::isLoggedIn()`). Rotas `POST`/`PUT`/`DELETE` exigem token CSRF nativo do Nextcloud (`OC.requestToken`).
- **Ciclo de Vida da Sessão de Reprodução**:
  1. **Abertura (`POST /api/v1/sessions`)**: Antes de criar, expira sessões vencidas (`expires_at < now`) do usuário; verifica `max_concurrent_sessions` (se > 0 e atingido, responde `429 Too Many Requests` com lista resumida de sessões ativas); cria registro `'active'` em `ncvp_playback_sessions` e retorna `sessionToken`, `expiresAt` e último progresso salvo em `ncvp_playback_progress`.
  2. **Streaming (`GET /api/v1/stream/{rootId}?path=...&sessionToken=...`)**: Valida que o `sessionToken` pertence ao `userId` autenticado, está `'active'`/`'paused'` e não expirou (`expires_at >= now`), valida o arquivo via `SafeNodeResolver` e chama **`OCP\ISession::close()`** antes de abrir o loop de leitura em chunks de 64 KiB ou o pipe `ffmpeg`.
  3. **Heartbeat (`PUT /api/v1/sessions/{token}/heartbeat`)**: Enviado a cada ~15–20s durante reprodução (e imediatamente em `pause`/`seeked`); renova `last_heartbeat_at` e `expires_at`, atualiza `status` (`active`/`paused`) e persiste `position_seconds`/`duration_seconds`/`completed` em `ncvp_playback_progress`.
  4. **Encerramento (`DELETE /api/v1/sessions/{token}` e `POST /api/v1/sessions/{token}/close`)**: Chamado ao fechar o player, trocar de vídeo ou descarregar a página (`fetch(..., { keepalive: true })` / `navigator.sendBeacon` para `POST .../close`), marcando `status = 'ended'` e salvando o progresso final.
  5. **Logout no Nextcloud (`UserLoggedOutListener`)**: Escuta `OCP\User\Events\UserLoggedOutEvent` registrado em `Application::register()` e marca todas as sessões `'active'`/`'paused'` daquele usuário como `'revoked'`.
  6. **Limpeza Periódica (`SessionCleanupJob`)**: `OCP\BackgroundJob\TimedJob` (intervalo de 15 min) marca sessões com `expires_at < now` como `'expired'` e remove registros encerrados/expirados mais antigos que 30 dias para evitar crescimento indefinido da tabela.

### 4. Isolamento de Caminhos (`SafeNodeResolver`)
- Toda rota de catálogo, busca, legenda, miniatura, sessão e streaming recebe `rootKey` (`rootId`) + `relativePath`.
- Rejeita bytes nulos, `..`, barras invertidas `\`, ou caminhos absolutos injetados em `relativePath`; resolve `rootKey` apenas dentre as raízes autorizadas e legíveis pelo usuário atual via `FolderConfigService` + `IRootFolder`, garantindo que o caminho normalizado final permaneça estritamente sob o prefixo da raiz.

### 5. Contrato HTTP / REST (`appinfo/routes.php`)
| Método | Rota | Controller | Escopo / Finalidade |
| :--- | :--- | :--- | :--- |
| `GET` | `/` | `PageController::index` | Renderiza interface principal com CSP estrita |
| `GET` | `/api/v1/roots` | `ConfigController::listRoots` | Retorna raízes ativas mescladas (`global` + `user`), capacidades e versão |
| `POST` | `/api/v1/roots` | `ConfigController::addUserRoots` | Persiste novas pastas pessoais em `ncvp_folders` |
| `DELETE` | `/api/v1/roots/{rootId}` | `ConfigController::removeUserRoot` | Remove pasta pessoal de `ncvp_folders` (bloqueia raízes `global`) |
| `GET` | `/api/v1/picker` | `ConfigController::browseUserFolders` | Lista subpastas da árvore do usuário no Nextcloud para seleção |
| `GET` | `/api/v1/catalog` | `CatalogController::listFolder` | Lista subpastas, vídeos (enriquecidos com progresso de `ncvp_playback_progress`) e breadcrumbs |
| `GET` | `/api/v1/search` | `CatalogController::search` | Busca case-insensitive por nome de vídeo em todas as raízes ativas |
| `GET` | `/api/v1/subtitles/{rootId}` | `CatalogController::serveSubtitle` | Serve legenda `.vtt` ou converte `.srt` → WebVTT em memória |
| `POST` | `/api/v1/sessions` | `SessionController::start` | Inicia sessão em `ncvp_playback_sessions` (valida limite simultâneo) |
| `GET` | `/api/v1/sessions` | `SessionController::listActive` | Lista sessões ativas do usuário autenticado |
| `PUT` | `/api/v1/sessions/{token}/heartbeat` | `SessionController::heartbeat` | Renova TTL da sessão e persiste progresso em `ncvp_playback_progress` |
| `DELETE` | `/api/v1/sessions/{token}` | `SessionController::close` | Encerra/revoga sessão do usuário (ou revoga sessão específica da lista) |
| `POST` | `/api/v1/sessions/{token}/close` | `SessionController::closeBeacon` | Encerramento resiliente via `keepalive`/`sendBeacon` ao fechar aba |
| `GET` | `/api/v1/preferences` | `PreferenceController::get` | Consulta preferências em `ncvp_user_preferences` |
| `PUT` | `/api/v1/preferences` | `PreferenceController::update` | Atualiza preferências em `ncvp_user_preferences` |
| `GET` | `/api/v1/stream/{rootId}` | `StreamController::stream` | Streaming HTTP Range (`206`) ou `?transcode=1&start=<sec>` (exige `sessionToken` válido + `ISession::close()`) |
| `GET` | `/api/v1/probe/{rootId}` | `StreamController::probe` | Retorna duração/codecs via `ffprobe` (quando FFmpeg habilitado) |
| `GET` | `/api/v1/thumb/{rootId}` | `StreamController::thumbnail` | Retorna miniatura via Preview API do Nextcloud ou cache isolado em `sys_get_temp_dir()` |
| `GET` | `/api/v1/admin/settings` | `SettingsController::getSettings` | Consulta pastas globais, FFmpeg e política de sessões (somente Admin) |
| `PUT` | `/api/v1/admin/settings` | `SettingsController::saveSettings` | Salva pastas globais em `ncvp_folders` e parâmetros em `IConfig` (somente Admin) |

### 6. Implementação e Publicação no Catálogo Oficial do Nextcloud (Nextcloud App Store)
- **Conformidade de `appinfo/info.xml`**:
  - Validado no CI contra o schema oficial `https://apps.nextcloud.com/schema/apps/info.xsd` via `xmllint --schema`.
  - Campos obrigatórios preenchidos: `<id>nextcloud_video_player</id>`, `<name>`, `<summary>`, `<description>`, `<version>`, `<licence>agpl</licence>`, `<author>`, `<namespace>NextcloudVideoPlayer</namespace>`, `<category>multimedia</category>`, `<website>`, `<bugs>`, `<repository type="git">`, `<screenshot>` (apontando para imagens PNG em `screenshots/` via raw GitHub URL), `<dependencies><php min-version="8.2" max-version="8.4" /><nextcloud min-version="30" max-version="32" /></dependencies>`, `<navigations>` e `<settings><admin>`.
- **Registro Único do App e Certificado (Code Signing)**:
  1. Gerar chave privada RSA 4096 e CSR (`nextcloud_video_player.key` / `nextcloud_video_player.csr` com `CN=nextcloud_video_player`).
  2. Registrar o app em `https://apps.nextcloud.com/developer/apps/register` submetendo o CSR para receber o certificado assinado (`nextcloud_video_player.crt`) e gerar o API Token em *My Account → API-Token*.
  3. Armazenar **exclusivamente** como Secrets no GitHub Actions: `NC_APP_PRIVATE_KEY`, `NC_APP_CERTIFICATE` e `NC_APPSTORE_TOKEN`.
- **Pipeline Automatizado de Release & App Store (`release-on-main` em `.github/workflows/ci.yml`)**:
  1. Montar diretório temporário limpo `nextcloud_video_player/` contendo apenas arquivos de runtime (`appinfo/`, `lib/`, `js/`, `css/`, `templates/`, `img/`, `l10n/`, `LICENSE`, `README.md`, `CHANGELOG.md`).
  2. Se `NC_APP_PRIVATE_KEY` e `NC_APP_CERTIFICATE` estiverem definidos: gerar `appinfo/signature.json` (assinatura de integridade de cada arquivo do pacote compatível com `occ integrity:check-app`) **antes** de compactar.
  3. Compactar `nextcloud_video_player-${TAG}.tar.gz` e publicar como asset da **GitHub Release** (`gh release create`), obtendo sua URL pública HTTPS.
  4. Se `NC_APPSTORE_TOKEN`, `NC_APP_PRIVATE_KEY` e `NC_APP_CERTIFICATE` estiverem definidos: assinar o `.tar.gz` (`openssl dgst -sha512 -sign key.pem tarball.tar.gz | openssl base64 -A`) e publicar na API oficial via `POST https://apps.nextcloud.com/api/v1/apps/releases` com cabeçalho `Authorization: Token ${NC_APPSTORE_TOKEN}` e payload JSON `{ "download": "<url>", "signature": "<base64>", "nightly": false }`.
  5. Caso os segredos ainda não existam, concluir a GitHub Release normalmente e emitir `::warning::` orientando quais segredos configurar.

### 7. Frontend Vanilla JS Modular e Compatibilidade CSP
- Módulos ES em `js/modules/` sem `eval`, sem inline `<script>`/`onclick` e sem recursos externos.
- Variáveis CSS nativas do Nextcloud (`--color-main-background`, `--color-background-hover`, `--color-main-text`, `--color-border`, `--color-primary-element`) com suporte automático a temas claro e escuro.
- Preferências e progresso sincronizados via API com o banco do servidor; `localStorage` usado apenas como cache otimista local caso haja micro-quedas de conectividade.

## Risks / Trade-offs

- **[Limites de tamanho de chave de índice no MariaDB/MySQL `utf8mb4`]** → Uso de coluna `path_hash` (`varchar(64)`, SHA-256) nos índices únicos em vez de indexar diretamente `varchar(1024)`.
- **[Bloqueio de sessão PHP durante streaming longo]** → Liberação explícita via `OCP\ISession::close()` após autenticação/autorização e antes do streaming em chunks ou pipe do `ffmpeg`.
- **[Sessões órfãs após fechamento abrupto do navegador]** → Combinação de encerramento via `keepalive`/`sendBeacon`, expiração por TTL (`expires_at`) verificada sob demanda e `SessionCleanupJob` periódico.
- **[Validação rigorosa da Nextcloud App Store (integrity check & XSD)]** → Validação `xmllint --schema` executada em todo PR/push e assinatura `appinfo/signature.json` executada somente após a montagem final dos arquivos do pacote, nunca alterando arquivos pós-assinatura.
- **[Arquivos grandes em External Storage remoto com FFmpeg]** → Transcodificação mantida como opt-in pelo Admin e protegida pelo limite de sessões simultâneas por usuário.

## Migration Plan

1. **Instalação via Catálogo Nextcloud (App Store) ou Tarball de Release**:
   - Via App Store: *Apps → Multimídia → Nextcloud Video Player → Baixar e ativar*.
   - Manual/offline: extrair `nextcloud_video_player-v<versão>.tar.gz` em `custom_apps/nextcloud_video_player` e executar `occ app:enable nextcloud_video_player`.
2. **Migração Automática de Banco**: Ao ativar o app, o Nextcloud executa `Version0001Date20261121000000` criando automaticamente as 4 tabelas `ncvp_*` no banco configurado (PostgreSQL, MariaDB/MySQL ou SQLite).
3. **Verificação de Integridade**: Executar `occ integrity:check-app nextcloud_video_player` para confirmar assinatura válida.
4. **Rollback Seguro**: `occ app:disable nextcloud_video_player` e remoção do diretório do app; nenhum arquivo de mídia do usuário é alterado em nenhuma etapa.
