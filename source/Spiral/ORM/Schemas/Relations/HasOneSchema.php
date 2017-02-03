<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Record;

/**
 * Has one is just one of the cases of HasMany. Relations like that used when parent record has one
 * child with [outer] key linked to value of [inner] key of parent mode.
 *
 * HasOne is identical to HasMany but uses different loader.
 *
 * Relation is not nullable by default (!).
 *
 * Example, [User has one Profile], user primary key is "id":
 * - relation will create outer key "user_id" in "profiles" table (or other table name), nullable
 *   by default
 * - relation will create index on column "user_id" in "profiles" table if allowed
 * - relation will create foreign key "profiles"."user_id" => "users"."id" if allowed
 */
class HasOneSchema extends HasManySchema
{
    /**
     * Relation type.
     */
    const RELATION_TYPE = Record::HAS_ONE;

    /**
     * Options needed in runtime (no need for where query).
     */
    const PACK_OPTIONS = [
        Record::INNER_KEY,
        Record::OUTER_KEY,
        Record::NULLABLE,
        Record::RELATION_COLUMNS,
        Record::MORPH_KEY
    ];
}