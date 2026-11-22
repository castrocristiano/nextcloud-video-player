# Spec Delta — session-and-persistence

## ADDED Requirements

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
