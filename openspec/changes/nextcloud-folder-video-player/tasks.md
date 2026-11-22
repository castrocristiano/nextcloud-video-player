# Tasks

## 1. Scaffolding do App Nextcloud e Tooling de Qualidade

- [ ] 1.1 Criar `appinfo/info.xml` para `nextcloud_video_player` (Nextcloud 30–32, PHP 8.2+, licença `AGPL-3.0-only`, ícone `img/app.svg` e registro da seção de administração) e validar XML bem-formado com `xmllint`
- [ ] 1.2 Criar `appinfo/routes.php` e `lib/AppInfo/Application.php` (`IBootstrap`, registro de navegação e DI) com `declare(strict_types=1);`
- [ ] 1.3 Configurar `composer.json`, `phpunit.xml` e `phpstan.neon` (PHPUnit 11 + PHPStan + stubs `christophwurst/nextcloud`) e verificar execução limpa de `vendor/bin/phpstan` e `vendor/bin/phpunit`
- [ ] 1.4 Configurar `package.json`, `eslint.config.js` e `vitest.config.js` (ESLint 9 + Vitest + `@vitest/coverage-v8` com saída `coverage/lcov.info` + `jsdom`) e verificar execução limpa de `npm run lint` e `npm run test:coverage`

## 2. Backend PHP — Configuração Híbrida de Pastas e Segurança (`folder-configuration`)

- [ ] 2.1 Implementar `SafeNodeResolver` em `lib/Service/SafeNodeResolver.php` (normalização de caminhos, bloqueio de *path traversal*, nul-bytes e validação estrita contra raízes configuradas legíveis) e cobrir com testes unitários PHPUnit em `tests/Unit/Service/SafeNodeResolverTest.php`
- [ ] 2.2 Implementar `FolderConfigService` em `lib/Service/FolderConfigService.php` (persistência via `OCP\IConfig` para raízes globais de Admin e raízes pessoais por usuário, mesclagem deduplicada e filtragem por permissões nativas do Nextcloud) e cobrir com `tests/Unit/Service/FolderConfigServiceTest.php`
- [ ] 2.3 Implementar `SettingsController`, `AdminSection` e `AdminSettings` (`lib/Controller/SettingsController.php`, `lib/Settings/`) com controle estrito de permissão Admin (`403` para não-admins) e testes unitários em `tests/Unit/Controller/SettingsControllerTest.php`
- [ ] 2.4 Implementar `ConfigController` em `lib/Controller/ConfigController.php` (`GET /api/v1/roots`, `POST /api/v1/roots`, `DELETE /api/v1/roots/{rootId}`, `GET /api/v1/picker`) impedindo remoção de raízes globais por usuários comuns e cobrir com `tests/Unit/Controller/ConfigControllerTest.php`

## 3. Backend PHP — Catálogo de Vídeos e Legendas (`video-catalog`)

- [ ] 3.1 Implementar `SubtitleService` em `lib/Service/SubtitleService.php` (detecção de arquivos `.vtt`/`.srt` irmãos pelo nome base, extração de rótulos de idioma e conversão `.srt` → WebVTT em memória) e cobrir com `tests/Unit/Service/SubtitleServiceTest.php`
- [ ] 3.2 Implementar `VideoCatalogService` em `lib/Service/VideoCatalogService.php` (listagem filtrada de subpastas e vídeos reconhecidos, ocultação de arquivos ocultos/não-vídeo, geração de *breadcrumbs*, ordenação por nome/data e busca case-insensitive em todas as raízes ativas com limite defensivo) e cobrir com `tests/Unit/Service/VideoCatalogServiceTest.php`
- [ ] 3.3 Implementar `CatalogController` em `lib/Controller/CatalogController.php` (`GET /api/v1/catalog`, `GET /api/v1/search`, `GET /api/v1/subtitles/{rootId}`) com isolamento de raízes e testes em `tests/Unit/Controller/CatalogControllerTest.php`

## 4. Backend PHP — Streaming HTTP Range, FFmpeg Opcional e Miniaturas (`video-streaming-player`)

- [ ] 4.1 Implementar `MediaStreamService` em `lib/Service/MediaStreamService.php` (parsing/validação completa de cabeçalhos HTTP `Range`, cálculo de `206 Partial Content` e `416 Range Not Satisfiable`, e leitura em chunks somente-leitura via stream da VFS) e cobrir com `tests/Unit/Service/MediaStreamServiceTest.php`
- [ ] 4.2 Implementar `FfmpegService` em `lib/Service/FfmpegService.php` (detecção de binários, `ffprobe` de duração, transcodificação opcional MP4 fragmentado em memória via `proc_open` sem shell interpolation e geração de miniaturas em diretório temporário fora da mídia) e cobrir com `tests/Unit/Service/FfmpegServiceTest.php`
- [ ] 4.3 Implementar `StreamController` em `lib/Controller/StreamController.php` (`GET /api/v1/stream/{rootId}`, `GET /api/v1/probe/{rootId}`, `GET /api/v1/thumb/{rootId}`) com liberação de sessão PHP antes de transmitir chunks, fallback de miniatura via Preview API do Nextcloud e testes em `tests/Unit/Controller/StreamControllerTest.php`
- [ ] 4.4 Implementar `PageController` em `lib/Controller/PageController.php` configurando `ContentSecurityPolicy` estrita (`default-src 'self'`, `media-src 'self' blob:`, sem CDNs externos) e carregando scripts/estilos locais

## 5. Frontend Vanilla JS + CSS Integrado ao Nextcloud

- [ ] 5.1 Criar templates `templates/main.php` e `templates/admin.php` e o ícone local `img/app.svg` sem scripts/estilos inline (100% compatível com CSP do Nextcloud)
- [ ] 5.2 Criar `css/style.css` portando o layout responsivo e o modo tela cheia estilo YouTube do `lan-video-player` utilizando variáveis CSS nativas do Nextcloud (suporte automático a temas claro e escuro)
- [ ] 5.3 Implementar módulos JS base `js/modules/api.js` (CSRF `OC.requestToken` e `OC.generateUrl`), `js/modules/formatters.js` e `js/modules/state.js` (preferências e progresso por `userId` em `localStorage` com regra de conclusão >= 95%)
- [ ] 5.4 Implementar `js/modules/catalog.js` e `js/modules/picker.js` (abas de raízes globais/pessoais, *breadcrumbs*, cartões com miniatura e barra de progresso, busca, ordenação, estado vazio e modal de seletor visual de pastas)
- [ ] 5.5 Implementar `js/modules/player.js`, `js/main.js` e `js/admin.js` (player HTML5 com *seek* Range/FFmpeg, auto-ocultamento de controles em tela cheia após 2,5s, velocidade `0.5x`–`2.0x`, atalhos de teclado/TV, legendas `.vtt`/`.srt`, seletor de áudio com fallback, retomada com aviso de reinício e próximo vídeo automático com contagem regressiva de 5s)
- [ ] 5.6 Criar testes unitários Vitest em `tests/js/` cobrindo `formatters`, `state` (retomada/95% conclusão), `catalog` (ordenação/estado vazio) e `player` (atalhos/contagem regressiva/fallback de formato) com geração de `coverage/lcov.info`

## 6. Validação Final, Documentação e Quality Gate

- [ ] 6.1 Executar suíte completa local (`openspec validate --all --strict --no-interactive`, `php -l`, `phpstan`, `phpunit` com Clover, `eslint`, `vitest` com LCOV) e confirmar 0 erros
- [ ] 6.2 Atualizar `README.md` e `wiki/` com instruções de instalação no Nextcloud (`apps/nextcloud_video_player`, `occ app:enable`), configuração de pastas Admin/Usuário e uso do player
- [ ] 6.3 Validar Quality Gate do SonarQube via `./sonar_check.sh` (usando `SONAR_HOST_URL` e `SONAR_TOKEN` via variáveis de ambiente) e verificar execução completa da esteira `.github/workflows/ci.yml`

## Workflow follow-up

- Após concluir todas as tarefas e validações de revisão, arquivar a mudança com `/openspec-archive-change` para sincronizar `openspec/specs/`.
