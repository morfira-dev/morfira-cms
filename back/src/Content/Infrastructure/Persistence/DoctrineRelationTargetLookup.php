<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Persistence;

use Cms\Schema\Application\RelationTargetLookup;
use Doctrine\DBAL\Connection;

final readonly class DoctrineRelationTargetLookup implements RelationTargetLookup
{
    public function __construct(private Connection $connection) {}
    public function exists(string $entryId,string $contentTypeId):bool{return $this->connection->fetchOne('SELECT 1 FROM content_entries WHERE id=? AND content_type_id=?',[$entryId,$contentTypeId])!==false;}
}
