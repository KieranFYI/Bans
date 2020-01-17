<?php

namespace Kieran\Bans\Entity;
    
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Ban extends Entity
{

	public function canView() {
		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return false;
		}
	
		return $visitor->hasPermission('bans', 'bans_view');
	}

	public function createFirst($message='') {
		$note = $this->newNote();
		$note->user_id = $this->admin_user_id;
		$note->timestamp = $this->timestamp;
		$note->data = [
			'Target UID' => $this->getSteamid32(),
			'Target IP' => $this->target_ip,
			'Target Name' => $this->target_name,
			'Ban Length' => $this->length,
			'Ban Reason' => $this->ban_reason,
			'Ban Type' => $this->Type->name,
		];
		$note->note = $message;
		$note->save();	
		$this->Notes[] = $note;
	}

	public function associateUser() {
        if ($this->getIdentityTypeRepo() == null) {
            return;
        }

        $type = $this->getIdentityTypeRepo()->findIdentityType('steam');
        
        if ($type == null) {
            return;
        }
        
		$identity = $this->getIdentityRepo()->findIdentityByValueByType($this->admin_id, $type->identity_type_id);
		if ($identity !== null && $identity->user_id > 0) {
			$this->admin_user_id = $identity->user_id;
			$this->save();
		}
	}

	public function newNote() {
		$visitor = \XF::visitor();
		if (!$visitor->user_id) {
			return null;
		}
		$note = $this->em()->create('Kieran\Bans:BanNote');
		$note->ban_id = $this->ban_id;
		$note->user_id = $visitor->user_id;

		return $note;
	}

	public function getSteamid32() {
		if (is_numeric($this->target_id) && strlen($this->target_id) >= 16) {
			$z = bcdiv(bcsub($this->target_id, '76561197960265728'), '2');
		} elseif (is_numeric($this->target_id)) {
			$z = bcdiv($this->target_id, '2'); // Actually new User ID format
		} else {
			return $this->target_id; // We have no idea what this is, so just return it.
		}
		$y = bcmod($this->target_id, '2');
		return 'STEAM_0:' . $y . ':' . floor($z);
	}
	
	public function getDraftReply()
	{
		return \XF\Draft::createFromEntity($this, 'DraftReplies');
	}

	public function getRemaining() {
		if ($this->ban_status == 1) {
			return 'Unbanned';
		}
		if ($this->ban_length === 0) {
			return 'Permanent';
		}
		$expire = $this->timestamp + ($this->ban_length * 60);

		if ($expire < time()) {
			return 'Expired';
		}
		$remaining = ceil(($expire - time()) / 60);
		return number_format($remaining) .' minutes';
	}

	public function getRemainingTime() {
		if ($this->ban_status == 1) {
			return -1;
		}
		if ($this->ban_length === 0) {
			return 0;
		}
		$expire = $this->timestamp + ($this->ban_length * 60);

		if ($expire < time()) {
			return -2;
		}
		$remaining = ceil(($expire - time()) / 60);
		return $remaining;
	}

	public function getLength() {
		if ($this->ban_length === 0) {
			return 'Permanent';
		}

		return number_format($this->ban_length) .' minutes';
	}

	public function getCreatedBy() {

		if (!$ban->admin_user_id) {
			$this->associateUser();
		}

		return $this->em()->find('XF:User', $this->admin_user_id);
	}

	public function getStatusIcon() {
		switch ($this->ban_review) {
			case 1:
				return 'check';

			case 2:
				return 'clock-o';

			case 3:
				return 'times';

			default:
				return 'question';
		}
	}

	public function getStatus() {
		switch ($this->ban_review) {
			case 1:
				return 'Approved';

			case 2:
				return 'Awaiting Response';

			case 3:
				return 'Disapproved';

			default:
				return 'Unchecked';
		}
	}

    public static function getStructure(Structure $structure)
	{
        $structure->table = 'xf_kieran_bans';
        $structure->shortName = 'Kieran\Bans:Ban';
        $structure->primaryKey = 'ban_id';
        $structure->columns = [
			'ban_id' =>  ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => false, 'changeLog' => false],
			'server_ip' => ['type' => self::STR, 'maxLength' => 55, 'default' => 'website'],
			'admin_id' => ['type' => self::BINARY, 'maxLength' => 64],
			'admin_user_id' => ['type' => self::UINT, 'maxLength' => 11, 'default' => 0],
			'admin_ip' => ['type' => self::STR, 'maxLength' => 55],
			'admin_name' => ['type' => self::STR, 'maxLength' => 64],
			'target_id' => ['type' => self::BINARY, 'maxLength' => 64, 'required' => true],
			'target_ip' => ['type' => self::STR, 'maxLength' => 55, 'default' => '0.0.0.0'],
			'target_name' => ['type' => self::STR, 'maxLength' => 65, 'required' => true],
			'ban_length' => ['type' => self::UINT, 'maxLength' => 11],
			'ban_reason' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
			'ban_status' => ['type' => self::UINT, 'maxLength' => 1, 'default' => 0],
			'type_id' => ['type' => self::STR, 'required' => true, 'nullable' => false, 'maxLength' => 25],
			'ban_review' => ['type' => self::UINT, 'maxLength' => 1, 'default' => 0],
			'timestamp' => ['type' => self::UINT, 'default' => \XF::$time],
        ];

        $structure->getters = [
			'draft_reply' => true,
			'steamid32' => true,
			'remaining' => true,
			'remaining_time' => true,
			'created_by' => true,
			'length' => true,
			'status_icon' => true,
			'status' => true,
		];
		$structure->relations = [
			'DraftReplies' => [
				'entity' => 'XF:Draft',
				'type' => self::TO_MANY,
				'conditions' => [
					['draft_key', '=', 'ban-', '$ban_id']
				],
				'key' => 'user_id'
			],
			'Notes' => [
				'entity' => 'Kieran\Bans:BanNote',
				'type' => self::TO_MANY,
				'conditions' => 'ban_id',
				'order' => 'note_id'
			],
            'Type' => [
                'entity' => 'Kieran\Bans:BanType',
                'type' => self::TO_ONE,
                'conditions' => 'type_id'
            ],
		];
        
        return $structure;
    }

	protected function getIdentityRepo() {
		return $this->repository('Kieran\Identity:Identity');
	}

	protected function getIdentityTypeRepo() {
		return $this->repository('Kieran\Identity:IdentityType');
	}
}