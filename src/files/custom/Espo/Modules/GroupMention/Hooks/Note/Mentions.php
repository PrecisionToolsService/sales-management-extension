<?php

namespace Espo\Modules\GroupMention\Hooks\Note;

use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\ORM\Entity;
use Espo\Core\Acl;
use Espo\Core\Acl\Permission;
use Espo\Core\AclManager;
use Espo\ORM\EntityManager;
use Espo\Entities\User;
use Espo\Entities\Note;
use Espo\Tools\Notification\Service;
use Espo\Core\Utils\Log;

use stdClass;

class Mentions
{
    public static int $order = 8;


    public function __construct(
        private Service $service,
        private EntityManager $entityManager,
        private User $user,
        private Acl $acl,
        private Log $log,
        private AclManager $aclManager
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options): void
    {
        if (!empty($options[SaveOption::SILENT])) {
            return;
        }

        assert($entity instanceof Note);

        if ($entity->getType() !== Note::TYPE_POST) {
            return;
        }

        $this->process($entity);
    }

    private function process(Note $note): void
    {
        $mentionData = (object) [];

        $previousMentionList = [];

        if (!$note->isNew()) {
            $previousMentionList = array_keys(get_object_vars($note->getData()->mentions ?? (object) []));
        }

        $matches = null;

        preg_match_all('/\[@(.+?)\]\(#(\w+)\/view\/([\w-]+)\)/', $note->getPost() ?? '', $matches);

        $mentionCount = 0;

        if (!empty($matches[0]) && is_array($matches[0])) {
            $keys = ['raw', 'name', 'type', 'id'];  // 各配列が対応するキー名

            $result = [];

            for ($i = 0; $i < count($matches[0]); $i++) {
                $obj = new stdClass();
                foreach ($keys as $k => $keyName) {
                    $obj->{$keyName} = $matches[$k][$i];
                }
                $result[] = $obj;
            }
            $mentionCount = $this->processMatches($result, $note, $mentionData, $previousMentionList);
        }

        $data = $note->getData();

        if ($mentionCount) {
            $data->mentions = $mentionData;
        } else {
            unset($data->mentions);
        }

        $note->setData($data);
    }

    /**
     * @param stdClass[] $matchList
     * @param string[] $previousMentionList
     */
    private function processMatches(
        array $matchList,
        Note $note,
        stdClass $mentionData,
        array $previousMentionList
    ): int {

        $mentionCount = 0;

        foreach ($matchList as $item) {
            $entityName = $item->name;
            $entityType = $item->type;
            $entityId = $item->id;

            $mentionData->$entityId = (object) [
                'id' => $entityId,
                'name' => $entityName,
                '_scope' => $entityType,
            ];

            $mentionCount++;

            if (in_array($item, $previousMentionList)) {
                continue;
            }
        }

        return $mentionCount;
    }
}
