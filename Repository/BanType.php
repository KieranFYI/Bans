<?php

namespace Kieran\Bans\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class BanType extends Repository
{
    public function getTypes()
    {
        return $this->finder('Kieran\Bans:BanType')->fetch();
    }

	public function setupBaseType()
	{
		return $this->em->create('Kieran\Bans:BanType');
	}
}