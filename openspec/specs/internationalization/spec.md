# internationalization Specification

## Purpose

Garante suporte nativo e extensível a internacionalização (i18n) para múltiplos idiomas no Nextcloud, adotando Inglês (`en`) como idioma padrão/fallback e entregando tradução completa em Português do Brasil (`pt_BR`) desde a versão inicial.

## Requirements

### Requirement: Default English language and automatic user locale detection
The system SHALL author all source interface strings and error messages in English (`en`) as the default language and automatically display the interface in the authenticated Nextcloud user's configured language preference.

#### Scenario: User with English locale opens the app
- **GIVEN** um usuário autenticado possui o idioma do perfil no Nextcloud configurado para Inglês (`en`)
- **WHEN** o usuário abre o aplicativo, o seletor de pastas, o player ou o painel de administração
- **THEN** todos os textos visíveis, botões, mensagens de erro, estados vazios e rótulos de acessibilidade são exibidos em Inglês (`en`)

#### Scenario: User with Brazilian Portuguese locale opens the app
- **GIVEN** um usuário autenticado possui o idioma do perfil no Nextcloud configurado para Português do Brasil (`pt_BR`)
- **WHEN** o usuário abre o aplicativo, o seletor de pastas, o player, o painel de sessões ou as configurações de administração
- **THEN** 100% da interface, pluralizações e mensagens de erro são exibidas em Português do Brasil (`pt_BR`) e datas são formatadas conforme o locale ativo

### Requirement: Graceful fallback to English for unsupported or partial locales
The system SHALL fall back transparently to English (`en`) whenever the user's configured Nextcloud language has no translation catalog available or when an individual translation key is missing from a partial language catalog.

#### Scenario: User with an untranslated locale accesses the app
- **GIVEN** um usuário autenticado utiliza um idioma no Nextcloud que ainda não possui catálogo de tradução incluído no aplicativo (por exemplo `ja` ou `de`)
- **WHEN** o usuário carrega o aplicativo e reproduz um vídeo
- **THEN** a interface é renderizada normalmente em Inglês (`en`) sem erros de script, textos em branco ou chaves cruas de tradução

#### Scenario: Partial translation catalog missing a specific key
- **GIVEN** um novo catálogo de idioma futuro contém tradução apenas para parte das mensagens do sistema
- **WHEN** a interface solicita uma mensagem ainda não traduzida naquele idioma
- **THEN** apenas a mensagem ausente é exibida em Inglês (`en`), preservando as demais strings já traduzidas naquele idioma

### Requirement: Extensible multi-language catalog architecture
The system SHALL decouple all translatable user-facing text from application logic using Nextcloud's standard localization catalog mechanism so additional languages can be added without modifying PHP or JavaScript business logic.

#### Scenario: Adding a new language catalog without code changes
- **GIVEN** um contribuidor adiciona arquivos de tradução para um novo idioma (por exemplo `es`) no diretório padrão de localização do aplicativo
- **WHEN** um usuário com esse idioma configurado acessa o aplicativo no Nextcloud
- **THEN** o sistema passa a exibir a interface no novo idioma automaticamente sem necessidade de alterar controllers, serviços ou módulos de frontend

### Requirement: Localized pluralization and regional date formatting
The system SHALL use locale-aware pluralization rules for countable UI elements and format dates according to the user's active Nextcloud locale without relying on external libraries or CDNs.

#### Scenario: Countable labels and dates adapt to active locale
- **GIVEN** uma pasta contém 1 vídeo ou 5 vídeos com data de modificação conhecida
- **WHEN** a listagem é renderizada para um usuário em `en` e para outro usuário em `pt_BR`
- **THEN** os contadores aplicam corretamente singular/plural no idioma ativo e as datas respeitam o formato regional do respectivo locale

### Requirement: Multi-language catalog metadata in Nextcloud App Store
The system SHALL provide application name, summary, and description in `appinfo/info.xml` with English (`en`) as the default language and localized entries for Brazilian Portuguese (`pt_BR`), ready to receive additional language entries.

#### Scenario: Viewing the app in the Nextcloud Apps catalog in different locales
- **GIVEN** o aplicativo está publicado no catálogo do Nextcloud com metadados padrão em inglês e tradução `pt_BR`
- **WHEN** administradores consultam o catálogo de aplicativos em `en` ou `pt_BR`
- **THEN** o catálogo apresenta o resumo e a descrição no idioma correspondente (e em inglês como padrão para idiomas ainda não traduzidos)
