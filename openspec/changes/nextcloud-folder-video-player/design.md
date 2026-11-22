# Design

## Context

Este design detalha a arquitetura técnica do aplicativo Nextcloud **`nextcloud_video_player`** (compatível com **Nextcloud 30–32**, **PHP 8.2+**, frontend **Vanilla JS + CSS**), portando as funcionalidades comprovadas do projeto [lan-video-player](https://github.com/castrocristiano/lan-video-player) para o ecossistema Nextcloud. Veja `proposal.md` para motivação e escopo, e os arquivos em `specs/*/spec.md` para os requisitos comportamentais.

Restrições fundamentais do ambiente alvo:
- Nextcloud 30–32 com PHP 8.2+ (`declare(strict_types=1);`), injeção de dependência via `OCP\AppFramework\Bootstrap\IBootstrap`, e proteção CSRF + CSP rigorosa (`default-src 'self'`).
- Acesso a arquivos exclusivamente pela camada virtual `OCP\Files\IRootFolder` para herdar permissões nativas, compartilhamentos e montagens de *External Storage*.
- Qualidade validada pela esteira CI/CD no padrão `contas` (PHPUnit + Clover, PHPStan, ESLint, Vitest + LCOV, `openspec validate --strict`, SonarQube Community Quality Gate).

## Goals / Non-Goals

**Goals:**
- Entregar um app Nextcloud nativo, leve e auto-contido (sem bundler pesado obrigatório no runtime), com código PHP tipado e testável via PHPUnit e frontend Vanilla JS ES modules testável via Vitest.
- Garantir isolamento rigoroso de acesso (somente pastas configuradas e acessíveis pelo usuário autenticado) e operação estritamente somente-leitura.
- Atingir streaming fluido com `HTTP 206 Partial Content` (Range) sobre fluxos da VFS do Nextcloud (`$file->fopen('r')`) e fallback opcional em memória via `ffmpeg`.
- Reaproveitar ao máximo a ergonomia visual e funcional do `lan-video-player`, adaptando cores e superfícies às variáveis CSS nativas do Nextcloud (temas claro e escuro).

**Non-Goals:**
- Não implementar escrita em disco nas pastas de vídeo (nem sidecars `.vtt`, nem cache de miniaturas dentro das pastas de mídia).
- Não criar tabelas SQL customizadas na v1 se `OCP\IConfig` atender perfeitamente às listas de pastas configuradas e flags de administração.
- Não usar `AppAPI` / microserviço externo na v1 (mantendo instalação simples via pasta em `apps/nextcloud_video_player` ou tarball de release).

## Decisions

### 1. Identificador e Estrutura do App Nextcloud
- **Decisão**: Usar `appId` = `nextcloud_video_player` e namespace PHP `OCA\NextcloudVideoPlayer`.
- **Justificativa**: O schema oficial do Nextcloud (`info.xsd`) recomenda `[a-z0-9_]` sem hífens para o `<id>` do app e nome da pasta em `apps/`. Alinha-se diretamente ao nome do repositório (`nextcloud-video-player`).
- **Estrutura de Diretórios**:
  ```text
  appinfo/
    info.xml                 # Metadados Nextcloud 30–32, AGPL-3.0-only, versão 0.1.0
    routes.php               # Rotas Web (page) e API REST JSON/Stream
  lib/
    AppInfo/
      Application.php        # IBootstrap: registra navegação, DI e Admin Settings
    Settings/
      AdminSection.php       # Seção no painel de Administração do Nextcloud
      AdminSettings.php      # ISettings para pastas globais e flags FFmpeg
    Controller/
      PageController.php     # Renderiza template principal com CSP estrita
      ConfigController.php   # CRUD de pastas pessoais + listagem de raízes híbridas + picker
      SettingsController.php # Salva/consulta configuração global (Admin only)
      CatalogController.php  # Listagem de pasta, breadcrumbs, busca global e legendas
      StreamController.php   # Streaming HTTP Range, probe/transcode FFmpeg e thumbnail
    Service/
      FolderConfigService.php   # Mescla raízes globais + pessoais via OCP\IConfig
      SafeNodeResolver.php      # Normalização, anti-traversal e checagem de raiz autorizada
      VideoCatalogService.php   # Filtragem de vídeos, ordenação, busca recursiva limitada
      SubtitleService.php       # Descoberta de .vtt/.srt irmãos e conversão SRT -> WebVTT em memória
      MediaStreamService.php    # Parsing/validação de cabeçalho HTTP Range e streaming em chunks
      FfmpegService.php         # Probe (ffprobe), transcode MP4 fragmentado (stdout) e thumbs em temp dir
  templates/
    main.php                 # Shell do catálogo e player (sem inline JS/CSS)
    admin.php                # Interface de configuração global de pastas e FFmpeg
  js/
    main.js                  # Entrypoint do app carregado via Util::addScript
    admin.js                 # Entrypoint das configurações de administração
    modules/
      api.js                 # Cliente HTTP com OC.requestToken (CSRF) e OC.generateUrl
      state.js               # Estado da biblioteca, preferências e progresso (localStorage + sync opcional)
      catalog.js             # Renderização de raízes, breadcrumbs, pastas, vídeos, busca e ordenação
      picker.js              # Modal de gerenciamento e seletor visual de pastas do Nextcloud
      player.js              # Motor do player HTML5, seek Range/FFmpeg, fullscreen estilo YouTube, atalhos, legendas e autoplay
      formatters.js          # Funções puras (bytes, datas, duração, parsing de legendas/progresso)
  css/
    style.css                # Layout responsivo usando var(--color-*) do Nextcloud
  img/
    app.svg                  # Ícone do app na barra superior do Nextcloud (SVG local)
  tests/
    Unit/                    # Testes PHPUnit (Services e Controllers com mocks OCP)
    js/                      # Testes Vitest (formatters, state, catalog, player)
  ```

### 2. Armazenamento de Configuração Híbrida via `OCP\IConfig`
- **Decisão**: Persistir pastas globais em `IConfig::getAppValue('nextcloud_video_player', 'global_folders', '[]')`, configurações de transcodificação em `ffmpeg_enabled` / `ffmpeg_path`, e pastas pessoais em `IConfig::getUserValue($userId, 'nextcloud_video_player', 'user_folders', '[]')`.
- **Alternativa considerada**: Criar tabelas dedicadas via `Migration` (`oc_ncvp_folders`). Descartada na v1 (YAGNI) porque as listas são pequenas (dezenas de pastas no máximo) e `IConfig` já fornece cache interno, escopo nativo por usuário e backup automático do Nextcloud sem complexidade de migração SQL.
- **Formato JSON das raízes configuradas**:
  ```json
  [
    { "id": "sha256-prefix-12", "path": "/Filmes", "label": "Filmes", "scope": "global" }
  ]
  ```
- **Deduplicação e Segurança**: Ao mesclar raízes `global` + `user`, os caminhos são normalizados (`/dir/sub`), deduplicados (priorizando o rótulo global se coincidir) e validados contra `$userFolder->get($path)` verificando `$node instanceof Folder` e `$node->isReadable()`. Pastas inexistentes ou sem permissão de leitura para aquele usuário são omitidas silenciosamente.

### 3. Isolamento de Caminhos (`SafeNodeResolver`)
- **Decisão**: Toda rota de catálogo, busca, legenda, miniatura e streaming recebe `rootId` + `relativePath` (ou `fileId` validado contra a árvore das raízes ativas do usuário).
- **Validação**:
  1. Rejeitar bytes nulos, `..`, barras invertidas `\`, ou caminhos absolutos injetados em `relativePath`.
  2. Resolver a raiz correspondente a `rootId` apenas dentro da lista efetiva de raízes configuradas e legíveis pelo usuário atual.
  3. Resolver o nó alvo via `$rootFolderNode->get($cleanRelativePath)` e confirmar que o caminho normalizado do nó permanece estritamente sob o prefixo da raiz autorizada.
- **Alternativa considerada**: Aceitar qualquer `fileId` do Nextcloud diretamente. Descartada porque permitiria que um usuário acessasse vídeos fora das pastas configuradas no plugin, violando o requisito central de restringir o player às pastas selecionadas.

### 4. Contrato HTTP / REST (`appinfo/routes.php`)
Todas as rotas ficam sob `/apps/nextcloud_video_player/` com autenticação obrigatória de usuário Nextcloud (sem `@PublicPage`) e proteção CSRF nativa para métodos mutáveis:

| Método | Rota | Controller | Escopo / Finalidade |
| :--- | :--- | :--- | :--- |
| `GET` | `/` | `PageController::index` | Renderiza a interface principal do app |
| `GET` | `/api/v1/roots` | `ConfigController::listRoots` | Retorna raízes ativas mescladas (`global` + `user`), capacidades (`ffmpegEnabled`) e versão |
| `POST` | `/api/v1/roots` | `ConfigController::addUserRoots` | Adiciona uma ou mais pastas pessoais selecionadas |
| `DELETE` | `/api/v1/roots/{rootId}` | `ConfigController::removeUserRoot` | Remove uma pasta pessoal (recusa remoção de raízes `global`) |
| `GET` | `/api/v1/picker` | `ConfigController::browseUserFolders` | Lista subpastas da árvore do usuário no Nextcloud para o seletor visual |
| `GET` | `/api/v1/catalog` | `CatalogController::listFolder` | Lista subpastas, vídeos e breadcrumbs de `rootId` + `path` com `sort` |
| `GET` | `/api/v1/search` | `CatalogController::search` | Busca case-insensitive por nome de vídeo em todas as raízes ativas |
| `GET` | `/api/v1/subtitles/{rootId}` | `CatalogController::serveSubtitle` | Serve legenda `.vtt` ou converte `.srt` → WebVTT em memória |
| `GET` | `/api/v1/stream/{rootId}` | `StreamController::stream` | Streaming HTTP Range (`206 Partial Content`) ou fallback `?transcode=1&start=<sec>` |
| `GET` | `/api/v1/probe/{rootId}` | `StreamController::probe` | Retorna duração/codecs via `ffprobe` (quando FFmpeg habilitado) |
| `GET` | `/api/v1/thumb/{rootId}` | `StreamController::thumbnail` | Retorna miniatura via Preview API do Nextcloud ou cache isolado em `sys_get_temp_dir()` |
| `GET` | `/api/v1/admin/settings` | `SettingsController::getSettings` | Consulta configuração global (somente Admin) |
| `PUT` | `/api/v1/admin/settings` | `SettingsController::saveSettings` | Salva pastas globais e opções de FFmpeg (somente Admin) |

### 5. Streaming HTTP Range e Transcodificação Opcional via FFmpeg
- **Streaming Direto (`MediaStreamService`)**:
  - Parse puro e testável do cabeçalho `Range` (suportando `bytes=start-end`, `bytes=start-` e `bytes=-suffix`, retornando `416 Range Not Satisfiable` com `Content-Range: bytes */<size>` para intervalos inválidos).
  - Resposta baseada em `CallbackResponse` / `StreamResponse` que fecha a sessão PHP (`session_write_close()` quando aplicável) antes de iniciar o loop de leitura em chunks de 64 KiB sobre `$file->fopen('r')` + `fseek()`, evitando bloqueio de outras requisições simultâneas do mesmo usuário no Nextcloud.
- **Transcodificação Opcional (`FfmpegService`)**:
  - Desabilitada por padrão ou ativável pelo Admin (`ffmpeg_enabled`), com detecção automática de disponibilidade de `ffmpeg`/`ffprobe` no servidor.
  - Executa `ffmpeg` com array de argumentos escapados (`proc_open`, sem shell interpolation) enviando MP4 fragmentado (`-movflags frag_keyframe+empty_moov+default_base_moof`) diretamente para `stdout`.
  - Para arquivos em armazenamento local, lê o caminho somente-leitura quando disponível; para armazenamentos remotos/External Storage sem caminho local direto, faz pipe somente-leitura via stream sem gravar dentro das pastas de mídia.

### 6. Frontend Vanilla JS Modular e Compatibilidade CSP
- **Decisão**: Portar a lógica do `lan-video-player/web/static/app.js` em módulos ES limpos dentro de `js/modules/` (sem `eval`, sem inline `<script>`/`onclick`, sem fontes/ícones externos).
- **Tema Nextcloud**: Mapear estilos para `var(--color-main-background)`, `var(--color-background-hover)`, `var(--color-main-text)`, `var(--color-text-maxcontrast)`, `var(--color-border)`, `var(--color-primary-element)` com fallback seguro para modo escuro em tela cheia.
- **Progresso de Reprodução**: Chaveado por `ncvp:progress:<userId>:<rootId>:<relativePath>` em `localStorage` para evitar colisão entre usuários diferentes no mesmo navegador e manter latência zero na atualização do progresso.

### 7. Estratégia de Testes e Qualidade (SonarQube)
- **PHP**: `composer.json` com `phpunit/phpunit` (^11.0), `phpstan/phpstan` (^2.0) e `christophwurst/nextcloud` (stubs oficiais do Nextcloud para PHP 8.2+/Nextcloud 30–32) permitindo rodar 100% dos testes unitários e análise estática em CI sem precisar subir uma instância completa do Nextcloud.
- **JavaScript**: `package.json` com `eslint` (^9) e `vitest` + `@vitest/coverage-v8` + `jsdom`, gerando `coverage/lcov.info` consumido diretamente pelo SonarQube.

## Risks / Trade-offs

- **[Bloqueio de sessão PHP durante streaming longo]** → Liberar o lock de sessão antes do streaming em chunks e limitar tamanho de buffer/flush para permitir navegação paralela na interface do Nextcloud enquanto o vídeo reproduz.
- **[Arquivos grandes em External Storage remoto (S3/SMB) com FFmpeg]** → `ffmpeg` sem arquivo local direto pode ter latência maior em *seek*; manter transcodificação explicitamente opcional (opt-in pelo Admin) e priorizar streaming Range nativo para `.mp4`/`.webm`/`.m4v`/`.mov`.
- **[Diferenças de API entre Nextcloud 30, 31 e 32]** → Utilizar exclusivamente interfaces públicas estáveis do namespace `OCP\*` (nunca `OC\*` privadas) e validar assinaturas via stubs `christophwurst/nextcloud` no PHPStan.
- **[Busca recursiva em pastas muito grandes]** → Aplicar limite defensivo de profundidade/resultados (ex.: máx. 500 resultados por consulta) e ignorar subárvores inacessíveis para manter tempo de resposta rápido.

## Migration Plan

1. Empacotamento via CI (`release-on-main`) gera `nextcloud_video_player-v<versão>.tar.gz`.
2. Instalação em instância Nextcloud: extrair em `custom_apps/nextcloud_video_player` (ou `apps/nextcloud_video_player`) e executar `occ app:enable nextcloud_video_player`.
3. Rollback seguro: `occ app:disable nextcloud_video_player` e remoção do diretório do app; nenhuma alteração em arquivos de mídia ou esquema SQL do Nextcloud é realizada.
