# Tasks

## 1. Scaffolding do App Nextcloud, Metadados App Store e Tooling de Qualidade

- [x] 1.1 Criar `appinfo/info.xml` completo para o catálogo da Nextcloud App Store (`nextcloud_video_player`, categoria `multimedia`, Nextcloud 30–32, PHP 8.2–8.4, licença `agpl`, metadados padrão em inglês `en` e localizados em `pt_BR`, `<navigations>`, `<settings><admin>`, `<website>`, `<bugs>`, `<repository>`, `<screenshot>`) e validar contra o XSD oficial (`https://apps.nextcloud.com/schema/apps/info.xsd`)
- [x] 1.2 Criar `appinfo/routes.php`, `lib/AppInfo/Application.php` (`IBootstrap`, registro de navegação com `OCP\IL10N`, DI, `AdminSettings`, `UserLoggedOutListener` e `SessionCleanupJob`), `img/app.svg` e `CHANGELOG.md`
- [x] 1.3 Configurar `composer.json`, `phpunit.xml` e `phpstan.neon` (PHPUnit 11 + PHPStan + stubs `christophwurst/nextcloud`) e verificar execução limpa de `vendor/bin/phpstan` e `vendor/bin/phpunit`
- [x] 1.4 Configurar `package.json`, `eslint.config.js` e `vitest.config.js` (ESLint 9 + Vitest + `@vitest/coverage-v8` com saída `coverage/lcov.info` + `jsdom`) e verificar execução limpa de `npm run lint` e `npm run test:coverage`

## 2. Banco de Dados e Migrações Nextcloud (`session-and-persistence`)

- [x] 2.1 Implementar migração `lib/Migration/Version0001Date20261121000000.php` (`OCP\Migration\SimpleMigrationStep`) criando as tabelas `ncvp_folders`, `ncvp_playback_progress`, `ncvp_user_preferences` e `ncvp_playback_sessions` com índices `path_hash` compatíveis com PostgreSQL, MariaDB/MySQL e SQLite, e testar esquema em `tests/Unit/Migration/Version0001MigrationTest.php`
- [x] 2.2 Implementar Entities e Mappers tipados (`FolderEntity`/`FolderMapper`, `PlaybackProgressEntity`/`PlaybackProgressMapper`, `UserPreferenceEntity`/`UserPreferenceMapper`, `PlaybackSessionEntity`/`PlaybackSessionMapper`) em `lib/Db/` usando `OCP\AppFramework\Db\QBMapper` e cobrir com testes unitários em `tests/Unit/Db/`

## 3. Backend PHP — Configuração Híbrida de Pastas e Segurança (`folder-configuration`)

- [x] 3.1 Implementar `SafeNodeResolver` em `lib/Service/SafeNodeResolver.php` (normalização de caminhos, bloqueio de *path traversal*, nul-bytes e validação estrita contra raízes configuradas legíveis) e cobrir com testes unitários em `tests/Unit/Service/SafeNodeResolverTest.php`
- [x] 3.2 Implementar `FolderConfigService` em `lib/Service/FolderConfigService.php` persistindo raízes globais e pessoais via `FolderMapper` (`ncvp_folders`), mesclando sem duplicidade e filtrando por permissões nativas do Nextcloud, com testes em `tests/Unit/Service/FolderConfigServiceTest.php`
- [x] 3.3 Implementar `SettingsController`, `AdminSection` e `AdminSettings` (`lib/Controller/SettingsController.php`, `lib/Settings/`) gerenciando pastas globais (`ncvp_folders`), flags FFmpeg e política de sessões (`session_ttl_seconds`, `max_concurrent_sessions`) com restrição estrita a Admins (`403` para não-admins) e testes em `tests/Unit/Controller/SettingsControllerTest.php`
- [x] 3.4 Implementar `ConfigController` em `lib/Controller/ConfigController.php` (`GET /api/v1/roots`, `POST /api/v1/roots`, `DELETE /api/v1/roots/{rootId}`, `GET /api/v1/picker`) impedindo remoção de raízes globais por usuários comuns e cobrir com `tests/Unit/Controller/ConfigControllerTest.php`

## 4. Backend PHP — Controle de Sessão, Progresso e Preferências (`session-and-persistence`)

- [x] 4.1 Implementar `ProgressService` e `PreferenceService` em `lib/Service/` (regras de progresso com conclusão >= 95%, sincronização multi-dispositivo via `ncvp_playback_progress` e preferências via `ncvp_user_preferences`) e cobrir com testes em `tests/Unit/Service/ProgressServiceTest.php` e `PreferenceServiceTest.php`
- [x] 4.2 Implementar `PlaybackSessionService` em `lib/Service/PlaybackSessionService.php` (abertura com token CSPRNG, expiração sob demanda, limite configurável de sessões simultâneas com exceção para `429`, validação de ownership, renovação via *heartbeat* integrada ao `ProgressService`, encerramento e revogação em lote) e cobrir com `tests/Unit/Service/PlaybackSessionServiceTest.php`
- [x] 4.3 Implementar `SessionController` (`POST/GET /api/v1/sessions`, `PUT /api/v1/sessions/{token}/heartbeat`, `DELETE /api/v1/sessions/{token}`, `POST /api/v1/sessions/{token}/close`) e `PreferenceController` (`GET/PUT /api/v1/preferences`) com isolamento estrito por `user_id` e testes em `tests/Unit/Controller/`
- [x] 4.4 Implementar `UserLoggedOutListener` (`lib/Listener/UserLoggedOutListener.php`) revogando sessões ativas ao fazer logout no Nextcloud e `SessionCleanupJob` (`lib/BackgroundJob/SessionCleanupJob.php` estendendo `TimedJob`) para expirar sessões vencidas e limpar histórico antigo, com testes unitários em `tests/Unit/`

## 5. Backend PHP — Catálogo, Legendas, Streaming HTTP Range e FFmpeg (`video-catalog`, `video-streaming-player`)

- [x] 5.1 Implementar `SubtitleService` em `lib/Service/SubtitleService.php` (detecção de `.vtt`/`.srt` irmãos pelo nome base, extração de rótulos de idioma e conversão `.srt` → WebVTT em memória) e cobrir com `tests/Unit/Service/SubtitleServiceTest.php`
- [x] 5.2 Implementar `VideoCatalogService` em `lib/Service/VideoCatalogService.php` (listagem filtrada de subpastas e vídeos enriquecidos com progresso de `ncvp_playback_progress`, ocultação de arquivos ocultos/não-vídeo, *breadcrumbs*, ordenação e busca case-insensitive em todas as raízes ativas) e cobrir com `tests/Unit/Service/VideoCatalogServiceTest.php`
- [x] 5.3 Implementar `CatalogController` em `lib/Controller/CatalogController.php` (`GET /api/v1/catalog`, `GET /api/v1/search`, `GET /api/v1/subtitles/{rootId}`) com testes em `tests/Unit/Controller/CatalogControllerTest.php`
- [x] 5.4 Implementar `MediaStreamService` e `FfmpegService` em `lib/Service/` (parsing/validação completa de `Range` para `206`/`416`, leitura em chunks somente-leitura, `ffprobe`, transcodificação opcional MP4 fragmentado via `proc_open` sem shell interpolation e cache isolado de miniaturas) com testes em `tests/Unit/Service/`
- [x] 5.5 Implementar `StreamController` (`GET /api/v1/stream/{rootId}`, `GET /api/v1/probe/{rootId}`, `GET /api/v1/thumb/{rootId}`) validando `sessionToken` ativo e invocando `OCP\ISession::close()` antes de iniciar a transferência, além de `PageController` com `ContentSecurityPolicy` estrita (`default-src 'self'`, `media-src 'self' blob:`) e testes em `tests/Unit/Controller/`

## 6. Frontend Vanilla JS + CSS Integrado ao Nextcloud

- [x] 6.1 Criar templates `templates/main.php` (catálogo, player, modal de pastas e modal de sessões ativas) e `templates/admin.php` (pastas globais, FFmpeg, TTL e limite de sessões) com 100% das strings em inglês (`en`) passando por `$l->t()`/`$l->n()` e sem scripts/estilos inline
- [x] 6.2 Criar `css/style.css` portando o layout responsivo e o modo tela cheia estilo YouTube do `lan-video-player` utilizando variáveis CSS nativas do Nextcloud (suporte automático a temas claro e escuro)
- [x] 6.3 Implementar `js/modules/api.js` (CSRF `OC.requestToken` e `OC.generateUrl`), `js/modules/i18n.js` (`OC.L10N` / `t()` / `n()` / `getLocale()` com fallback para `en`), `js/modules/formatters.js` (formatação regional de datas por locale), `js/modules/state.js` (sincronizado com `/api/v1/preferences` e progresso vindo do servidor) e `js/modules/session.js` (abertura, *heartbeat* periódico, tratamento de `429`, encerramento via `keepalive`/`sendBeacon` e painel de sessões ativas)
- [x] 6.4 Implementar `js/modules/catalog.js` e `js/modules/picker.js` (abas de raízes globais/pessoais, *breadcrumbs*, cartões com miniatura e barra de progresso sincronizada, busca, ordenação, estado vazio e seletor visual de pastas, todos internacionalizados via `i18n.js`)
- [x] 6.5 Implementar `js/modules/player.js`, `js/main.js` e `js/admin.js` (player HTML5 com *seek* Range/FFmpeg autenticado por `sessionToken`, auto-ocultamento de controles em tela cheia após 2,5s, velocidade `0.5x`–`2.0x`, atalhos de teclado/TV, legendas `.vtt`/`.srt`, seletor de áudio com fallback, retomada sincronizada e próximo vídeo automático com contagem regressiva de 5s, todos internacionalizados via `i18n.js`)
- [x] 6.6 Criar os catálogos completos de tradução `l10n/pt_BR.json` e `l10n/pt_BR.js` (cobrindo 100% das strings PHP/templates/JS em inglês `en`, incluindo pluralizações) e adicionar testes automatizados de consistência de chaves, pluralização, formatação de datas por locale e fallback para `en`
- [x] 6.7 Criar testes unitários Vitest em `tests/js/` cobrindo `i18n`, `formatters`, `state`, `session` (*heartbeat*, `429`, `keepalive`/revogação), `catalog` e `player` com geração de `coverage/lcov.info`

## 7. Distribuição no Catálogo Nextcloud App Store e Quality Gate (`appstore-distribution`)

- [x] 7.1 Atualizar `.github/workflows/ci.yml` adicionando validação XSD de `appinfo/info.xml` (`xmllint --schema`), empacotamento limpo somente-runtime, assinatura de integridade (`appinfo/signature.json` + SHA-512 do `.tar.gz` via `NC_APP_PRIVATE_KEY` e `NC_APP_CERTIFICATE`) e etapa condicional de publicação na API da Nextcloud App Store (`NC_APPSTORE_TOKEN`)
- [x] 7.2 Gerar e adicionar capturas de tela em `screenshots/` (temas claro e escuro) referenciadas em `appinfo/info.xml` e documentar o processo completo de registro do CSR, geração de certificado e configuração dos GitHub Secrets em `README.md` e `wiki/`
- [x] 7.3 Executar suíte completa local (`openspec validate --all --strict --no-interactive`, `xmllint --schema`, `php -l`, `phpstan`, `phpunit` com Clover, `eslint`, `vitest` com LCOV) e confirmar 0 erros
- [ ] 7.4 Validar Quality Gate do SonarQube via `./sonar_check.sh` (usando `SONAR_HOST_URL` e `SONAR_TOKEN` via variáveis de ambiente) e verificar execução completa da esteira `.github/workflows/ci.yml`

## Workflow follow-up

- Após concluir todas as tarefas e validações de revisão, arquivar a mudança com `/openspec-archive-change` para sincronizar `openspec/specs/`.
