# Spec Delta

## Purpose

Especifica os requisitos de conformidade, metadados, empacotamento limpo, assinatura criptográfica e publicação automatizada do aplicativo no catálogo oficial da Nextcloud App Store (`apps.nextcloud.com`).

## ADDED Requirements

### Requirement: Nextcloud App Store compliant metadata and catalog assets
The system SHALL provide an `appinfo/info.xml` validated strictly against the official Nextcloud App Store XSD schema, declaring valid category `multimedia`, Nextcloud 30–32 and PHP 8.2+ compatibility, AGPL v3.0 license, default English (`en`) and Brazilian Portuguese (`pt_BR`) localized metadata, navigation and admin settings entries, repository/issue links, and light/dark catalog screenshots.

#### Scenario: Validating app metadata against official XSD schema
- **GIVEN** o repositório contém `appinfo/info.xml`, ícone local do app e referências de capturas de tela em `screenshots/`
- **WHEN** a validação da esteira de CI verifica `appinfo/info.xml` contra o schema oficial da Nextcloud App Store
- **THEN** o XML passa com zero erros de schema, categoria `multimedia` válida e versão sincronizada com a release

#### Scenario: App listed in Nextcloud administration catalog and navigation
- **GIVEN** o aplicativo está instalado a partir do catálogo do Nextcloud em uma instância Nextcloud 30–32
- **WHEN** o administrador habilita o aplicativo em *Apps → Multimídia*
- **THEN** o aplicativo aparece com nome, resumo, descrição, ícone e capturas de tela corretos, registra sua entrada no menu de navegação principal e disponibiliza sua página nas Configurações de Administração

### Requirement: Clean runtime-only release tarball packaging
The system SHALL package each release as a deterministic `.tar.gz` archive rooted at the app directory `nextcloud_video_player/` containing only runtime production files and excluding development, test, specification, and CI artifacts.

#### Scenario: Inspecting a packaged release archive
- **GIVEN** a esteira de release na branch `main` empacota uma nova versão do aplicativo
- **WHEN** o arquivo `nextcloud_video_player-v<versão>.tar.gz` é gerado
- **THEN** o pacote contém apenas os diretórios e arquivos necessários para execução (`appinfo/`, `lib/`, `js/`, `css/`, `templates/`, `img/`, `l10n/`, `LICENSE`, `README.md`, `CHANGELOG.md`) e exclui `tests/`, `openspec/`, `.github/`, `wiki/`, `node_modules/` e arquivos locais de ambiente

### Requirement: Cryptographic code signing and release signature
The system MUST sign the packaged application to generate `appinfo/signature.json` for Nextcloud code integrity verification and produce a Base64 SHA-512 cryptographic signature of the release tarball using private signing credentials provided exclusively via CI secrets or environment variables.

#### Scenario: Signing package before publishing to App Store
- **GIVEN** os segredos criptográficos de assinatura (`NC_APP_PRIVATE_KEY` e `NC_APP_CERTIFICATE`) estão configurados na esteira de CI
- **WHEN** uma nova release é construída na branch `main`
- **THEN** o pipeline gera `appinfo/signature.json` antes de criar o `.tar.gz`, assina o tarball final com SHA-512 em Base64 e nunca grava nem versiona chaves privadas no repositório

#### Scenario: Nextcloud integrity check passes after installation
- **GIVEN** um administrador instala o pacote assinado a partir da Nextcloud App Store ou da GitHub Release
- **WHEN** o Nextcloud executa a verificação de integridade de código (`occ integrity:check-app nextcloud_video_player`)
- **THEN** o aplicativo é aprovado sem avisos de arquivos inválidos, ausentes ou adulterados

### Requirement: Automated publishing to Nextcloud App Store API
The system SHALL integrate conditional automated publishing to the Nextcloud App Store releases API (`apps.nextcloud.com/api/v1/apps/releases`) into the `main` release workflow using the public GitHub Release tarball URL, tarball signature, and an API token provided via secrets.

#### Scenario: Automated App Store publication when secrets are configured
- **GIVEN** uma release na branch `main` publicou o tarball assinado na GitHub Release e os segredos `NC_APPSTORE_TOKEN`, `NC_APP_PRIVATE_KEY` e `NC_APP_CERTIFICATE` estão definidos
- **WHEN** a etapa de publicação na App Store é executada
- **THEN** o pipeline envia a URL de download do tarball e sua assinatura criptográfica para a API oficial da Nextcloud App Store registrando a nova versão no catálogo

#### Scenario: Graceful fallback before App Store credentials are provisioned
- **GIVEN** os segredos da Nextcloud App Store ainda não foram cadastrados no repositório GitHub
- **WHEN** uma release é criada na branch `main`
- **THEN** o pipeline publica normalmente a GitHub Release com o tarball instalável e registra um aviso informativo indicando quais segredos configurar para ativar o envio automático à App Store
