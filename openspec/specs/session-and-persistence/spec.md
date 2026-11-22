# session-and-persistence Specification

## Purpose

Define o modelo de persistência relacional em tabelas dedicadas do banco do Nextcloud e o controle completo de sessões autenticadas de reprodução, concorrência, expiração e revogação sem bloqueio durante o streaming.

## Requirements

### Requirement: Dedicated database tables managed by Nextcloud migrations
The system SHALL store configured folders, per-user playback progress, per-user player preferences, and playback session records in dedicated Nextcloud database tables created and upgraded via official Nextcloud migrations compatible with PostgreSQL, MariaDB/MySQL, and SQLite.

#### Scenario: Tables created automatically when enabling the app
- **GIVEN** o aplicativo é instalado em uma instância Nextcloud utilizando PostgreSQL, MariaDB/MySQL ou SQLite
- **WHEN** o administrador habilita o aplicativo pela primeira vez
- **THEN** o Nextcloud executa a migração criando as tabelas dedicadas de pastas, progresso, preferências e sessões com seus índices de integridade e sem gravar nenhum arquivo nas pastas de mídia

#### Scenario: Cross-device persistence of user progress and preferences
- **GIVEN** um usuário autenticado assistiu parcialmente a um vídeo e definiu velocidade `1.25x` no navegador desktop
- **WHEN** o mesmo usuário abre o aplicativo em outro dispositivo (celular ou TV) autenticado na mesma conta Nextcloud
- **THEN** o sistema carrega das tabelas do servidor o progresso salvo e as preferências de reprodução do usuário

### Requirement: Playback session lifecycle and heartbeat persistence
The system SHALL create a tracked playback session record whenever an authenticated user starts watching a video, renew its expiration via periodic heartbeats that also persist current playback progress, and close the session cleanly when playback stops or expires.

#### Scenario: Starting, heartbeating, and closing a playback session
- **GIVEN** um usuário autenticado abre um vídeo autorizado no player
- **WHEN** o player inicia a sessão de reprodução, envia heartbeats periódicos durante a exibição e encerra a sessão ao fechar o player ou a aba
- **THEN** o sistema cria um registro ativo com token único e prazo de expiração, atualiza o timestamp de atividade e o progresso do vídeo na tabela de progresso a cada heartbeat, e marca a sessão como encerrada ao término

#### Scenario: Automatic expiration of abandoned sessions
- **GIVEN** uma sessão ativa deixa de receber heartbeats após queda de rede ou fechamento abrupto do navegador e ultrapassa seu prazo de expiração
- **WHEN** ocorre a rotina periódica de limpeza ou uma nova consulta de sessões ativas
- **THEN** o sistema marca a sessão expirada como inativa e libera sua vaga de concorrência para novas reproduções

### Requirement: Authentication, CSRF, user isolation, and logout revocation
The system MUST require a valid authenticated Nextcloud session on every endpoint, enforce CSRF protection on state-changing requests, isolate all session, preference, and progress records strictly to the owning user, and revoke active playback sessions when the user logs out of Nextcloud.

#### Scenario: User attempts to manipulate another user's session or progress
- **GIVEN** o usuário A possui uma sessão de reprodução ativa e registros de progresso salvos
- **WHEN** o usuário B autenticado envia uma requisição tentando consultar, renovar ou encerrar a sessão ou alterar o progresso do usuário A
- **THEN** o sistema recusa a requisição (`403 Forbidden` ou `404 Not Found`) sem revelar nem modificar os dados do usuário A

#### Scenario: Active playback sessions revoked on Nextcloud logout
- **GIVEN** um usuário autenticado possui uma ou mais sessões de reprodução ativas registradas na tabela de sessões
- **WHEN** esse usuário encerra sua sessão (logout) no Nextcloud
- **THEN** o sistema revoga automaticamente as sessões ativas desse usuário e bloqueia tentativas subsequentes de streaming usando os tokens revogados

### Requirement: Configurable concurrent session limit and user session control
The system SHALL allow administrators to configure a session TTL and an optional maximum number of concurrent active playback sessions per user, rejecting excess sessions with `429 Too Many Requests` while allowing users to view and terminate their own active sessions.

#### Scenario: Exceeding the configured concurrent session limit
- **GIVEN** o administrador configurou o limite de 2 sessões simultâneas por usuário e o usuário já possui 2 sessões ativas não expiradas
- **WHEN** o usuário tenta iniciar uma terceira reprodução simultânea
- **THEN** o sistema recusa a abertura da nova sessão com status `429 Too Many Requests`, informando o limite vigente e permitindo ao usuário encerrar uma sessão ativa existente

#### Scenario: User revokes one of their own active sessions
- **GIVEN** um usuário autenticado visualiza sua lista de sessões de reprodução ativas
- **WHEN** o usuário solicita o encerramento de uma sessão específica
- **THEN** o sistema marca aquela sessão como revogada imediatamente, liberando a cota de sessões simultâneas

### Requirement: Non-blocking PHP session handling during media streaming
The system MUST release the PHP session lock immediately after authentication and authorization checks before streaming video chunks or transcoded media pipes so concurrent requests from the same user remain responsive.

#### Scenario: Navigating and sending heartbeats while streaming a large video
- **GIVEN** um usuário autenticado está assistindo a um vídeo em streaming contínuo (HTTP Range ou transcodificação FFmpeg)
- **WHEN** o cliente envia heartbeats periódicos ou o usuário navega em outra aba do Nextcloud simultaneamente
- **THEN** as requisições paralelas são respondidas normalmente sem aguardar o encerramento da conexão de streaming

### Requirement: Persistence of last catalog location and subtitle font scale
The system SHALL persist each user's last accessed library root ID, last relative folder path, and subtitle font scale preference in the dedicated user preferences database table via a Nextcloud schema migration, maintaining strict per-user isolation and read-only media guarantees.

#### Scenario: Schema migration adds new preference columns cleanly
- **GIVEN** o aplicativo já possui a tabela `ncvp_user_preferences` criada pela migração inicial em PostgreSQL, MariaDB/MySQL ou SQLite
- **WHEN** o Nextcloud executa a nova migração incremental de banco de dados
- **THEN** a tabela passa a conter as colunas `last_root_id`, `last_folder_path` e `subtitle_scale` com valores padrão seguros sem perda das preferências já existentes

#### Scenario: Rejecting invalid or out-of-bounds preference updates
- **GIVEN** um usuário autenticado envia uma atualização para `/api/v1/preferences` com `subtitleScale` fora do intervalo permitido ou `lastFolderPath` contendo caracteres de path traversal (`..` ou `\0`)
- **WHEN** o backend valida a requisição
- **THEN** o servidor normaliza/limita `subtitleScale` para o intervalo válido (`0.75`–`2.0`), rejeita ou sanitiza caminhos inválidos e nunca grava nada fora da tabela do próprio usuário
