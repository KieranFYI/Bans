<?php

namespace Kieran\Bans\Entity;
    
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class BanType extends Entity
{

    public static function getStructure(Structure $structure)
	{
        $structure->table = 'xf_kieran_bans_type';
        $structure->shortName = 'Kieran\Bans:BanType';
        $structure->primaryKey = 'type_id';
        $structure->columns = [
			'type_id' => ['type' => self::STR, 'required' => true, 'nullable' => false, 'maxLength' => 25],
			'name' => ['type' => self::STR, 'maxLength' => 55]
        ];
		$structure->relations = [
			'Bans' => [
				'entity' => 'Kieran\Bans:Ban',
				'type' => self::TO_MANY,
				'conditions' => 'type_id'
			],
		];
        
        return $structure;
    }
}