<?php

namespace Kieran\Bans\Entity;
    
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class BanNote extends Entity
{
	public function canView() {
		
		return $this->Ban->canView();
	}

	public function isAttachmentEmbedded() {
		return false;
	}

	public function associateAttachments($hash)
	{
		/** @var \XF\Service\Attachment\Preparer $inserter */
		$inserter = $this->app()->service('XF:Attachment\Preparer');
		$associated = $inserter->associateAttachmentsWithContent($hash, 'ban', $this->note_id);
		if ($associated)
		{
			$this->fastUpdate('attach_count', $this->attach_count + $associated);
		}
	}

    public static function getStructure(Structure $structure)
	{
        $structure->table = 'xf_kieran_bans_notes';
        $structure->shortName = 'Kieran\Bans:BanNote';
        $structure->primaryKey = 'note_id';
        $structure->columns = [
			'note_id' =>  ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => false, 'changeLog' => false],
			'ban_id' => ['type' => self::UINT, 'maxLength' => 11],
			'user_id' => ['type' => self::UINT, 'maxLength' => 11],
			'visible' => ['type' => self::UINT, 'maxLength' => 1, 'default' => 1],
			'note' => ['type' => self::STR, 'default' => ''],
			'attach_count' => ['type' => self::UINT, 'max' => 65535, 'forced' => true, 'default' => 0],
			'data' => ['type' => self::JSON_ARRAY, 'default' => []],
			'timestamp' => ['type' => self::UINT, 'default' => \XF::$time],
        ];
        $structure->getters = [
		];
		$structure->relations = [
			'Attachments' => [
				'entity' => 'XF:Attachment',
				'type' => self::TO_MANY,
				'conditions' => [
					['content_type', '=', 'ban'],
					['content_id', '=', '$note_id']
				],
				'with' => 'Data',
				'order' => 'attach_date'
			],
			'Ban' => [
				'entity' => 'Kieran\Bans:Ban',
				'type' => self::TO_ONE,
				'conditions' => 'ban_id',
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
		];
        
        return $structure;
    }
}