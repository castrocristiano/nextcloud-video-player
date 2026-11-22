# Spec Delta

## Purpose

Permite configurar e gerenciar de forma híbrida as pastas selecionadas de vídeo no Nextcloud, combinando pastas pré-configuradas pelo administrador e pastas pessoais escolhidas por cada usuário em modo estritamente somente-leitura.

## ADDED Requirements

### Requirement: Admin global folder configuration
The system SHALL allow Nextcloud administrators to configure a list of global pre-configured video folder paths and optional display labels in the application administration settings, persisted in the application's dedicated database table outside any user media folder.

#### Scenario: Admin adds valid global folders
- **GIVEN** um administrador autenticado acessa as configurações de administração do aplicativo no Nextcloud
- **WHEN** o administrador informa um ou mais caminhos relativos de pastas válidas do Nextcloud (por exemplo `/Filmes` ou `/Midia/Series`) e salva a configuração
- **THEN** o sistema persiste os registros na tabela de pastas configuradas fora das pastas de mídia e passa a exibi-los como raízes pré-configuradas para os usuários que possuam permissão de leitura nessas pastas

#### Scenario: Non-admin attempts to modify global settings
- **GIVEN** um usuário comum sem privilégios administrativos está autenticado no Nextcloud
- **WHEN** esse usuário tenta enviar uma requisição para alterar as pastas globais configuradas
- **THEN** o sistema recusa a operação com status de acesso negado (`403 Forbidden`) e mantém a configuração global intacta

### Requirement: User personal folder selection
The system SHALL allow each authenticated Nextcloud user to browse their own accessible Nextcloud directory tree through a visual folder picker and add or remove personal video folders persisted in the application's database table without modifying any file or directory in storage.

#### Scenario: User adds personal folders via visual picker
- **GIVEN** um usuário autenticado abre o modal de gerenciamento de pastas da biblioteca
- **WHEN** o usuário navega pela sua árvore de pastas do Nextcloud, marca uma ou mais pastas e confirma a adição
- **THEN** o sistema persiste os registros vinculados exclusivamente àquele usuário na tabela de pastas configuradas e exibe imediatamente as pastas adicionadas na biblioteca ativa

#### Scenario: User removes a personal folder from the library
- **GIVEN** o usuário possui uma pasta pessoal vinculada à sua biblioteca de vídeos
- **WHEN** o usuário clica para desvincular essa pasta na tela de gerenciamento
- **THEN** o registro é removido da tabela de pastas configuradas do usuário, a pasta deixa de ser exibida na biblioteca e nenhum arquivo ou diretório real no Nextcloud é alterado ou excluído

### Requirement: Hybrid root merging and permission filtering
The system SHALL combine global admin-configured folders with the user's personal folders into a single deduplicated list of library roots, exposing only folders that exist and are readable by the authenticated user under Nextcloud native permissions.

#### Scenario: User without permission on a global folder
- **GIVEN** o administrador configurou globalmente as pastas `/Filmes` e `/Treinamentos` e o usuário atual possui permissão de leitura apenas em `/Filmes`
- **WHEN** o usuário abre o aplicativo de player de vídeo
- **THEN** o sistema lista `/Filmes` como raiz disponível e omite silenciosamente `/Treinamentos` sem expor a existência ou conteúdo da pasta restrita

#### Scenario: Global and personal folder point to the same path
- **GIVEN** o caminho `/Filmes` está configurado tanto globalmente pelo administrador quanto na lista pessoal do usuário
- **WHEN** o sistema carrega as raízes ativas da biblioteca do usuário
- **THEN** o caminho `/Filmes` aparece uma única vez na listagem, preservando a indicação de origem administrativa e impedindo duplicidade

### Requirement: Path normalization and traversal protection
The system MUST normalize all configured folder paths and reject any path containing parent traversal segments, null bytes, or targets outside the authenticated user's accessible Nextcloud file tree.

#### Scenario: Malicious traversal path rejected
- **GIVEN** um usuário autenticado envia um pedido para adicionar ou consultar uma pasta com caminho manipulado como `../../etc` ou `/Filmes/../../../privado`
- **WHEN** o sistema valida o caminho informado
- **THEN** o sistema rejeita a requisição com erro de validação (`400 Bad Request` ou `403 Forbidden`) sem revelar caminhos internos do servidor
