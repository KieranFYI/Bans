<?php

namespace Kieran\Bans\Admin\Controller;

use XF\Mvc\ParameterBag;
use Kieran\Support\Entity\TicketType;

class Bans extends \XF\Admin\Controller\AbstractController
{

	public function actionTypes(ParameterBag $params)
	{
		return $this->rerouteController('Kieran\Bans:Types', 'index', $params);
	}
}