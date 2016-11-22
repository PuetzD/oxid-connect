<?php

namespace Makaira\Connect\Repository;

use Makaira\Connect\Change;
use Makaira\Connect\DatabaseInterface;
use Makaira\Connect\Result\Changes;
use Makaira\Connect\Type;
use Makaira\Connect\Type\Variant\Variant;
use Makaira\Connect\RepositoryInterface;

class VariantRepository implements RepositoryInterface
{
    /**
     * @var DatabaseInterface
     */
    private $database;

    /**
     * @var ModifierList
     */
    private $modifiers;

    protected $selectQuery = "
        SELECT
            makaira_connect_changes.sequence,
            makaira_connect_changes.oxid AS `id`,
            oxarticles.oxparentid AS `parent`,
            UNIX_TIMESTAMP(oxarticles.oxtimestamp) AS `timestamp`,
            oxarticles.*,
            oxartextends.oxlongdesc as `OXLONGDESC`,
            oxartextends.oxtags as `OXTAGS`
        FROM
            makaira_connect_changes
            LEFT JOIN oxarticles ON oxarticles.oxid = makaira_connect_changes.oxid
            LEFT JOIN oxartextends ON oxarticles.oxid = oxartextends.oxid
        WHERE
            (oxarticles.oxid is null OR oxarticles.oxparentid != '')
            AND makaira_connect_changes.sequence > :since
            AND makaira_connect_changes.type = 'variant'
        ORDER BY
            sequence ASC
        LIMIT :limit
    ";

    protected $touchQuery = "
        INSERT INTO
          makaira_connect_changes
        (OXID, TYPE, CHANGED)
          VALUES
        (:oxid, 'variant', NOW())
    ";

    protected $deleteQuery = "
        REPLACE INTO
          makaira_connect_deletions
        (OXID, TYPE, CHANGED)
          VALUES
        (:oxid, 'variant', NOW())
    ";

    protected $undeleteQuery = "
        DELETE FROM
          makaira_connect_deletions
        WHERE
          OXID = :oxid
          AND TYPE = 'variant'
    ";

    protected $isDeletedQuery = "
        SELECT * FROM
          makaira_connect_deletions
        WHERE
          OXID = :oxid
          AND TYPE = 'variant'
        LIMIT 1
    ";

    public function __construct(DatabaseInterface $database, ModifierList $modifiers)
    {
        $this->database = $database;
        $this->modifiers = $modifiers;
    }

    public function get($id)
    {
        return null;
    }

    /**
     * Fetch and serialize changes.
     * @param int $since Sequence offset
     * @param int $limit Fetch limit
     * @return Changes
     */
    public function getChangesSince($since, $limit = 50)
    {
        $result = $this->database->query($this->selectQuery, ['since' => $since, 'limit' => $limit]);

        $changes = array();
        foreach ($result as $row) {
            $change = new Change();
            $change->id = $row['id'];
            $change->sequence = $row['sequence'];
            unset($row['sequence']);

            if (!isset($row['OXID']) && $this->isDeleted($change->id)) {
                $change->deleted = true;
            } else {
                // @TODO: Do we want to pass the full product / changes list to the modifier to allow aggregated queries?
                $variant = new Variant($row);
                $variant = $this->modifiers->applyModifiers($variant, $this->database);
                $change->data = $variant;
            }
            $changes[] = $change;
        }

        return new Changes(array(
                               'type' => 'variant',
                               'since' => $since,
                               'count' => count($changes),
                               'changes' => $changes,
                           ));
    }

    /**
     * Mark an object as updated.
     * @param string $oxid
     * @codeCoverageIgnore
     */
    public function touch($oxid)
    {
        $this->database->execute($this->touchQuery, ['oxid' => $oxid]);
        $this->database->execute($this->undeleteQuery, ['oxid' => $oxid]);
    }

    /**
     * Mark an object as deleted.
     * @param string $oxid
     * @codeCoverageIgnore
     */
    public function delete($oxid)
    {
        $this->database->execute($this->touchQuery, ['oxid' => $oxid]);
        $this->database->execute($this->deleteQuery, ['oxid' => $oxid]);
    }

    /**
     * Check if an object has been marked as deleted.
     * @param string $oxid
     * @return bool
     */
    public function isDeleted($oxid)
    {
        $result = $this->database->query($this->isDeletedQuery, ['oxid' => $oxid]);
        return count($result) > 0;
    }
}
