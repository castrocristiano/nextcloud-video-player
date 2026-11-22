<?php

declare(strict_types=1);

namespace OCP {
    if (!interface_exists(IContainer::class)) {
        interface IContainer
        {
            /**
             * @template T of object
             * @param class-string<T> $id
             * @return T
             */
            public function get(string $id): object;
        }
    }

    if (!interface_exists(IServerContainer::class)) {
        interface IServerContainer extends IContainer
        {
        }
    }

    if (!interface_exists(IURLGenerator::class)) {
        interface IURLGenerator
        {
            public function linkToRoute(string $routeName, array $arguments = []): string;
            public function imagePath(string $appName, string $image): string;
        }
    }

    if (!interface_exists(INavigationManager::class)) {
        interface INavigationManager
        {
            public function add(callable|array $entry): void;
        }
    }

    if (!interface_exists(IL10N::class)) {
        interface IL10N
        {
            public function t(string $text, array $parameters = []): string;
            public function n(string $textSingular, string $textPlural, int $count, array $parameters = []): string;
            public function getLanguageCode(): string;
        }
    }
}

namespace OCP\L10N {
    if (!interface_exists(IFactory::class)) {
        interface IFactory
        {
            public function get(string $app, ?string $lang = null): \OCP\IL10N;
        }
    }
}

namespace OCP {

    if (!interface_exists(IConfig::class)) {
        interface IConfig
        {
            public function getAppValue(string $appName, string $key, string $default = ''): string;
            public function setAppValue(string $appName, string $key, string $value): void;
            public function getUserValue(string $userId, string $appName, string $key, string $default = ''): string;
            public function setUserValue(string $userId, string $appName, string $key, string $value): void;
            public function deleteUserValue(string $userId, string $appName, string $key): void;
        }
    }

    if (!interface_exists(IUser::class)) {
        interface IUser
        {
            public function getUID(): string;
        }
    }

    if (!interface_exists(IUserSession::class)) {
        interface IUserSession
        {
            public function isLoggedIn(): bool;
            public function getUser(): ?IUser;
        }
    }

    if (!interface_exists(ISession::class)) {
        interface ISession
        {
            public function close(): void;
        }
    }

    if (!interface_exists(IGroupManager::class)) {
        interface IGroupManager
        {
            public function isAdmin(string $userId): bool;
        }
    }

    if (!interface_exists(IRequest::class)) {
        interface IRequest
        {
            public function getHeader(string $name): string;
            public function getParam(string $key, mixed $default = null): mixed;
            public function getParams(): array;
            public function passesCSRFCheck(): bool;
        }
    }

    if (!interface_exists(IPreview::class)) {
        interface IPreview
        {
            public function getPreview(Files\File $file, int $width = -1, int $height = -1, bool $crop = false): ?IImage;
        }
    }

    if (!interface_exists(IImage::class)) {
        interface IImage
        {
            public function valid(): bool;
            public function data(): string;
            public function mimeType(): string;
        }
    }

    if (!interface_exists(IDBConnection::class)) {
        interface IDBConnection
        {
            public function getQueryBuilder(): DB\QueryBuilder\IQueryBuilder;
            public function lastInsertId(string $table = ''): int|string;
        }
    }

    if (!class_exists(Util::class)) {
        class Util
        {
            public static function addScript(string $application, ?string $name = null): void
            {
            }

            public static function addStyle(string $application, ?string $name = null): void
            {
            }
        }
    }
}

namespace OCP\DB {
    if (!class_exists(Types::class)) {
        class Types
        {
            public const BIGINT = 'bigint';
            public const INTEGER = 'integer';
            public const STRING = 'string';
            public const TEXT = 'text';
            public const FLOAT = 'float';
            public const BOOLEAN = 'boolean';
        }
    }

    if (!interface_exists(ISchemaWrapper::class)) {
        interface ISchemaWrapper
        {
            public function hasTable(string $name): bool;
            public function createTable(string $name): ITable;
        }
    }

    if (!interface_exists(ITable::class)) {
        interface ITable
        {
            public function addColumn(string $name, string $type, array $options = []): void;
            public function setPrimaryKey(array $columnNames, ?string $indexName = null): void;
            public function addUniqueIndex(array $columnNames, ?string $indexName = null): void;
            public function addIndex(array $columnNames, ?string $indexName = null): void;
        }
    }
}

namespace OCP\DB\QueryBuilder {
    if (!interface_exists(IExpressionBuilder::class)) {
        interface IExpressionBuilder
        {
            public const EQ = '=';
            public const NEQ = '<>';
            public const LT = '<';
            public const LTE = '<=';
            public const GT = '>';
            public const GTE = '>=';

            public function eq(mixed $x, mixed $y): string;
            public function neq(mixed $x, mixed $y): string;
            public function lt(mixed $x, mixed $y): string;
            public function lte(mixed $x, mixed $y): string;
            public function gt(mixed $x, mixed $y): string;
            public function gte(mixed $x, mixed $y): string;
            public function in(string $x, mixed $y, int $type = 0): string;
            public function andX(mixed ...$x): string;
            public function orX(mixed ...$x): string;
        }
    }

    if (!interface_exists(IQueryBuilder::class)) {
        interface IQueryBuilder
        {
            public const PARAM_INT = 1;
            public const PARAM_STR = 2;
            public const PARAM_BOOL = 5;
            public const PARAM_STR_ARRAY = 102;

            public function select(string ...$selects): self;
            public function from(string $from, ?string $alias = null): self;
            public function where(mixed ...$predicates): self;
            public function andWhere(mixed ...$predicates): self;
            public function orderBy(string $sort, ?string $order = null): self;
            public function setMaxResults(?int $maxResults): self;
            public function insert(string $table): self;
            public function values(array $values): self;
            public function update(string $table, ?string $alias = null): self;
            public function set(string $key, mixed $value): self;
            public function delete(string $table, ?string $alias = null): self;
            public function expr(): IExpressionBuilder;
            public function createNamedParameter(mixed $value, int $type = self::PARAM_STR, ?string $placeHolder = null): string;
            public function executeQuery(): IResult;
            public function executeStatement(): int;
            public function getLastInsertId(): int|string;
        }
    }

    if (!interface_exists(IResult::class)) {
        interface IResult
        {
            /** @return array<string, mixed>|false */
            public function fetch(): array|false;
            /** @return list<array<string, mixed>> */
            public function fetchAll(): array;
            public function closeCursor(): bool;
        }
    }
}

namespace OCP\Migration {
    if (!interface_exists(IOutput::class)) {
        interface IOutput
        {
            public function info(string $message): void;
        }
    }

    if (!class_exists(SimpleMigrationStep::class)) {
        abstract class SimpleMigrationStep
        {
            /**
             * @param \Closure(): \OCP\DB\ISchemaWrapper $schemaClosure
             * @param array<string, mixed> $options
             */
            public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?\OCP\DB\ISchemaWrapper
            {
                return null;
            }
        }
    }
}

namespace OCP\Files {
    if (!class_exists(NotFoundException::class)) {
        class NotFoundException extends \Exception
        {
        }
    }

    if (!class_exists(NotPermittedException::class)) {
        class NotPermittedException extends \Exception
        {
        }
    }

    if (!interface_exists(Node::class)) {
        interface Node
        {
            public function getId(): int;
            public function getName(): string;
            public function getPath(): string;
            public function getInternalPath(): string;
            public function getSize(): int;
            public function getMTime(): int;
            public function getMimeType(): string;
            public function isReadable(): bool;
            public function getParent(): Folder;
        }
    }

    if (!interface_exists(File::class)) {
        interface File extends Node
        {
            /** @return resource|false */
            public function fopen(string $mode);
            public function getContent(): string;
        }
    }

    if (!interface_exists(Folder::class)) {
        interface Folder extends Node
        {
            public function get(string $path): Node;
            public function nodeExists(string $path): bool;
            /** @return list<Node> */
            public function getDirectoryListing(): array;
            public function getRelativePath(string $path): ?string;
        }
    }

    if (!interface_exists(IRootFolder::class)) {
        interface IRootFolder extends Folder
        {
            public function getUserFolder(string $userId = ''): Folder;
        }
    }
}

namespace OCP\EventDispatcher {
    if (!class_exists(Event::class)) {
        abstract class Event
        {
        }
    }

    if (!interface_exists(IEventListener::class)) {
        /**
         * @template T of Event
         */
        interface IEventListener
        {
            /** @param T $event */
            public function handle(Event $event): void;
        }
    }
}

namespace OCP\User\Events {
    if (!class_exists(UserLoggedOutEvent::class)) {
        class UserLoggedOutEvent extends \OCP\EventDispatcher\Event
        {
            public function __construct(private ?\OCP\IUser $user = null)
            {
            }

            public function getUser(): ?\OCP\IUser
            {
                return $this->user;
            }
        }
    }
}

namespace OCP\BackgroundJob {
    if (!interface_exists(IJob::class)) {
        interface IJob
        {
        }
    }

    if (!class_exists(TimedJob::class)) {
        abstract class TimedJob implements IJob
        {
            protected int $interval = 0;

            public function __construct(mixed $time = null)
            {
            }

            protected function setInterval(int $interval): void
            {
                $this->interval = $interval;
            }

            public function getInterval(): int
            {
                return $this->interval;
            }

            abstract protected function run(mixed $argument): void;
        }
    }
}

namespace OCP\Settings {
    if (!interface_exists(ISettings::class)) {
        interface ISettings
        {
            public function getForm(): \OCP\AppFramework\Http\TemplateResponse;
            public function getSection(): string;
            public function getPriority(): int;
        }
    }

    if (!interface_exists(IIconSection::class)) {
        interface IIconSection
        {
            public function getID(): string;
            public function getName(): string;
            public function getPriority(): int;
            public function getIcon(): string;
        }
    }
}

namespace OCP\AppFramework\Bootstrap {
    if (!interface_exists(IRegistrationContext::class)) {
        interface IRegistrationContext
        {
            public function registerEventListener(string $event, string $listener): void;
            public function registerService(string $name, \Closure $closure): void;
        }
    }

    if (!interface_exists(IBootContext::class)) {
        interface IBootContext
        {
            public function getServerContainer(): \OCP\IServerContainer;
        }
    }

    if (!interface_exists(IBootstrap::class)) {
        interface IBootstrap
        {
            public function register(IRegistrationContext $context): void;
            public function boot(IBootContext $context): void;
        }
    }
}

namespace OCP\AppFramework {
    if (!class_exists(Http::class)) {
        class Http
        {
            public const STATUS_OK = 200;
            public const STATUS_CREATED = 201;
            public const STATUS_NO_CONTENT = 204;
            public const STATUS_PARTIAL_CONTENT = 206;
            public const STATUS_BAD_REQUEST = 400;
            public const STATUS_UNAUTHORIZED = 401;
            public const STATUS_FORBIDDEN = 403;
            public const STATUS_NOT_FOUND = 404;
            public const STATUS_CONFLICT = 409;
            public const STATUS_REQUEST_RANGE_NOT_SATISFIABLE = 416;
            public const STATUS_TOO_MANY_REQUESTS = 429;
            public const STATUS_INTERNAL_SERVER_ERROR = 500;
            public const STATUS_NOT_IMPLEMENTED = 501;
        }
    }
}

namespace OCP\AppFramework\Http {
    if (!class_exists(Http::class)) {
        class_alias(\OCP\AppFramework\Http::class, Http::class);
    }

    if (!class_exists(ContentSecurityPolicy::class)) {
        class ContentSecurityPolicy
        {
            /** @var list<string> */
            private array $allowedMediaDomains = [];
            /** @var list<string> */
            private array $allowedImgDomains = [];
            /** @var list<string> */
            private array $allowedScriptDomains = [];
            /** @var list<string> */
            private array $allowedStyleDomains = [];

            public function addAllowedMediaDomain(string $domain): self
            {
                $this->allowedMediaDomains[] = $domain;
                return $this;
            }

            public function addAllowedImageDomain(string $domain): self
            {
                $this->allowedImgDomains[] = $domain;
                return $this;
            }

            public function addAllowedScriptDomain(string $domain): self
            {
                $this->allowedScriptDomains[] = $domain;
                return $this;
            }

            public function addAllowedStyleDomain(string $domain): self
            {
                $this->allowedStyleDomains[] = $domain;
                return $this;
            }

            /** @return list<string> */
            public function getAllowedMediaDomains(): array
            {
                return $this->allowedMediaDomains;
            }

            /** @return list<string> */
            public function getAllowedImageDomains(): array
            {
                return $this->allowedImgDomains;
            }
        }
    }

    if (!interface_exists(ICallbackResponse::class)) {
        interface ICallbackResponse
        {
            public function callback(IOutput $output): void;
        }
    }

    if (!interface_exists(IOutput::class)) {
        interface IOutput
        {
            public function setOutput(string $output): void;
        }
    }

    if (!class_exists(Response::class)) {
        class Response
        {
            private int $status = Http::STATUS_OK;
            /** @var array<string, string> */
            private array $headers = [];
            private ?ContentSecurityPolicy $csp = null;

            public function setStatus(int $status): self
            {
                $this->status = $status;
                return $this;
            }

            public function getStatus(): int
            {
                return $this->status;
            }

            public function addHeader(string $name, string $value): self
            {
                $this->headers[$name] = $value;
                return $this;
            }

            /** @return array<string, string> */
            public function getHeaders(): array
            {
                return $this->headers;
            }

            public function setContentSecurityPolicy(ContentSecurityPolicy $csp): self
            {
                $this->csp = $csp;
                return $this;
            }

            public function getContentSecurityPolicy(): ?ContentSecurityPolicy
            {
                return $this->csp;
            }

            public function render(): string
            {
                return '';
            }
        }
    }

    if (!class_exists(DataResponse::class)) {
        class DataResponse extends Response
        {
            /** @param array<string, string> $headers */
            public function __construct(
                private mixed $data = [],
                int $statusCode = Http::STATUS_OK,
                array $headers = []
            ) {
                $this->setStatus($statusCode);
                foreach ($headers as $k => $v) {
                    $this->addHeader($k, $v);
                }
            }

            public function getData(): mixed
            {
                return $this->data;
            }
        }
    }

    if (!class_exists(TemplateResponse::class)) {
        class TemplateResponse extends Response
        {
            /** @param array<string, mixed> $params */
            public function __construct(
                private string $appName,
                private string $templateName,
                private array $params = [],
                private string $renderAs = 'user'
            ) {
            }

            public function getAppName(): string
            {
                return $this->appName;
            }

            public function getTemplateName(): string
            {
                return $this->templateName;
            }

            /** @return array<string, mixed> */
            public function getParams(): array
            {
                return $this->params;
            }

            public function getRenderAs(): string
            {
                return $this->renderAs;
            }
        }
    }
}

namespace OCP\AppFramework\Db {
    if (!class_exists(DoesNotExistException::class)) {
        class DoesNotExistException extends \Exception
        {
        }
    }

    if (!class_exists(MultipleObjectsReturnedException::class)) {
        class MultipleObjectsReturnedException extends \Exception
        {
        }
    }

    if (!class_exists(Entity::class)) {
        abstract class Entity
        {
            protected ?int $id = null;
            /** @var array<string, string> */
            private array $fieldTypes = ['id' => 'integer'];
            /** @var array<string, bool> */
            private array $updatedFields = [];

            public function getId(): ?int
            {
                return $this->id;
            }

            public function setId(?int $id): void
            {
                $this->id = $id === null ? null : (int) $id;
                $this->updatedFields['id'] = true;
            }

            protected function addType(string $fieldName, string $type): void
            {
                $this->fieldTypes[$fieldName] = $type;
            }

            /** @return array<string, bool> */
            public function getUpdatedFields(): array
            {
                return $this->updatedFields;
            }

            public function resetUpdatedFields(): void
            {
                $this->updatedFields = [];
            }

            protected function markFieldUpdated(string $attribute): void
            {
                $this->updatedFields[$attribute] = true;
            }

            public function slugify(string $attributeName): string
            {
                return strtolower((string) preg_replace('/([A-Z])/', '_$1', $attributeName));
            }

            public function columnToProperty(string $columnName): string
            {
                $parts = explode('_', $columnName);
                $property = array_shift($parts);
                foreach ($parts as $part) {
                    $property .= ucfirst($part);
                }
                return (string) $property;
            }

            /**
             * @param array<string, mixed> $row
             * @return static
             */
            public static function fromRow(array $row): static
            {
                /** @var static $instance */
                $instance = new static();
                foreach ($row as $col => $val) {
                    $prop = $instance->columnToProperty($col);
                    $setter = 'set' . ucfirst($prop);
                    if (property_exists($instance, $prop) || method_exists($instance, $setter)) {
                        $type = $instance->fieldTypes[$prop] ?? 'string';
                        if ($val !== null) {
                            $val = match ($type) {
                                'integer' => (int) $val,
                                'float' => (float) $val,
                                'boolean' => (bool) $val,
                                default => (string) $val,
                            };
                        }
                        $instance->$setter($val);
                    }
                }
                $instance->resetUpdatedFields();
                return $instance;
            }

            /**
             * @param string $name
             * @param list<mixed> $args
             */
            public function __call(string $name, array $args): mixed
            {
                if (str_starts_with($name, 'get')) {
                    $prop = lcfirst(substr($name, 3));
                    return $this->$prop ?? null;
                }
                if (str_starts_with($name, 'set')) {
                    $prop = lcfirst(substr($name, 3));
                    $val = $args[0] ?? null;
                    if ($val !== null && isset($this->fieldTypes[$prop])) {
                        $val = match ($this->fieldTypes[$prop]) {
                            'integer' => (int) $val,
                            'float' => (float) $val,
                            'boolean' => (bool) $val,
                            default => (string) $val,
                        };
                    }
                    $this->$prop = $val;
                    $this->markFieldUpdated($prop);
                    return null;
                }
                throw new \BadMethodCallException("Method {$name} does not exist");
            }
        }
    }

    if (!class_exists(QBMapper::class)) {
        /**
         * @template T of Entity
         */
        abstract class QBMapper
        {
            /**
             * @param class-string<T> $entityClass
             */
            public function __construct(
                protected \OCP\IDBConnection $db,
                protected string $tableName,
                protected string $entityClass = Entity::class
            ) {
            }

            public function getTableName(): string
            {
                return $this->tableName;
            }

            /**
             * @param T $entity
             * @return T
             */
            public function insert(Entity $entity): Entity
            {
                $qb = $this->db->getQueryBuilder();
                $qb->insert($this->tableName);
                $values = [];
                foreach (array_keys($entity->getUpdatedFields()) as $prop) {
                    if ($prop === 'id') {
                        continue;
                    }
                    $getter = 'get' . ucfirst($prop);
                    $col = $entity->slugify($prop);
                    $values[$col] = $qb->createNamedParameter($entity->$getter());
                }
                $qb->values($values);
                $qb->executeStatement();
                $entity->setId((int) $qb->getLastInsertId());
                $entity->resetUpdatedFields();
                return $entity;
            }

            /**
             * @param T $entity
             * @return T
             */
            public function update(Entity $entity): Entity
            {
                $updated = $entity->getUpdatedFields();
                if ($updated === []) {
                    return $entity;
                }
                $qb = $this->db->getQueryBuilder();
                $qb->update($this->tableName);
                foreach (array_keys($updated) as $prop) {
                    if ($prop === 'id') {
                        continue;
                    }
                    $getter = 'get' . ucfirst($prop);
                    $col = $entity->slugify($prop);
                    $qb->set($col, $qb->createNamedParameter($entity->$getter()));
                }
                $qb->where($qb->expr()->eq('id', $qb->createNamedParameter($entity->getId(), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
                $qb->executeStatement();
                $entity->resetUpdatedFields();
                return $entity;
            }

            /**
             * @param T $entity
             * @return T
             */
            public function delete(Entity $entity): Entity
            {
                $qb = $this->db->getQueryBuilder();
                $qb->delete($this->tableName)
                    ->where($qb->expr()->eq('id', $qb->createNamedParameter($entity->getId(), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
                $qb->executeStatement();
                return $entity;
            }

            /**
             * @return T
             */
            protected function findEntity(\OCP\DB\QueryBuilder\IQueryBuilder $qb): Entity
            {
                $result = $qb->executeQuery();
                $rows = $result->fetchAll();
                $result->closeCursor();
                if ($rows === []) {
                    throw new DoesNotExistException('Entity does not exist');
                }
                if (count($rows) > 1) {
                    throw new MultipleObjectsReturnedException('Multiple entities returned');
                }
                $class = $this->entityClass;
                /** @var T $entity */
                $entity = $class::fromRow($rows[0]);
                return $entity;
            }

            /**
             * @return list<T>
             */
            protected function findEntities(\OCP\DB\QueryBuilder\IQueryBuilder $qb): array
            {
                $result = $qb->executeQuery();
                $rows = $result->fetchAll();
                $result->closeCursor();
                $class = $this->entityClass;
                $entities = [];
                foreach ($rows as $row) {
                    /** @var T $entity */
                    $entity = $class::fromRow($row);
                    $entities[] = $entity;
                }
                return $entities;
            }
        }
    }
}

namespace OCP\AppFramework\Http\Attribute {
    if (!class_exists(NoAdminRequired::class)) {
        #[\Attribute(\Attribute::TARGET_METHOD)]
        class NoAdminRequired
        {
        }
    }

    if (!class_exists(NoCSRFRequired::class)) {
        #[\Attribute(\Attribute::TARGET_METHOD)]
        class NoCSRFRequired
        {
        }
    }
}

namespace OCP\AppFramework {
    if (!class_exists(App::class)) {
        class App
        {
            /** @param array<string, mixed> $urlParams */
            public function __construct(
                private string $appName,
                private array $urlParams = []
            ) {
            }

            public function getAppName(): string
            {
                return $this->appName;
            }
        }
    }

    if (!class_exists(Controller::class)) {
        abstract class Controller
        {
            public function __construct(
                protected string $appName,
                protected \OCP\IRequest $request
            ) {
            }
        }
    }
}
