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

class GroupMentions
{
    public static int $order = 10;


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

        $previousMentionList = [];

        if (!$note->isNew()) {
            $previousMentionList = array_keys(get_object_vars($note->getData()->mentions ?? (object) []));
        }

        $matches = null;

        preg_match_all('/\[(.+?)\]\(#(\w+)\/view\/([\w-]+)\)/', $note->getPost() ?? '', $matches);

        if (!empty($matches[0]) && is_array($matches[0])) {
            $MentionItems = $this->normalizeMatches($matches);
            $mentionData = $this->processMentions($MentionItems, $note, $previousMentionList);
        }

        $data = $note->getData();

        if (!$mentionData) {
            return;
        }
        if (!$data->mentions) {
            $data->mentions =  $mentionData;
        } else {
            $data->mentions = array_merge((array) $data->mentions, (array)  $mentionData);
        }

        $note->setData($data);
    }


    /**
     * @param [] $matches
     * @return stdClass[]
     */
    private function normalizeMatches(array $matches): array
    {
        $keys = ['raw', 'name', 'type', 'id'];  // 各配列が対応するキー名

        $result = [];

        for ($i = 0; $i < count($matches[0]); $i++) {
            $obj = new stdClass();
            foreach ($keys as $k => $keyName) {
                $obj->{$keyName} = $matches[$k][$i];
            }
            $result[] = $obj;
        }
        return $result;
    }

    /**
     * @param stdClass[] $mentionItems
     * @param string[] $previousMentionList
     * @return stdClass[]
     */
    private function processMentions(
        array $mentionItems,
        Note $note,
        array $previousMentionList
    ): object {

        $mentionData = (object) [];

        foreach ($mentionItems as $item) {
            $mentionName = "@" . $item->name;
            $entityName = $item->name;
            $entityType = $item->type;
            $entityId = $item->id;

            $mentionData->$mentionName = (object) [
                'id' => $entityId,
                'name' => $entityName,
                '_scope' => $entityType,
            ];

            if (in_array($item, $previousMentionList)) {
                continue;
            }
        }

        return $mentionData;
    }
}
