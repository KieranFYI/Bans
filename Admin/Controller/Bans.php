<?php

namespace Kieran\Bans\Admin\Controller;

use XF\Mvc\ParameterBag;

class Bans extends \XF\Admin\Controller\AbstractController
{

	public function actionTypes(ParameterBag $params)
	{
		return $this->rerouteController('Kieran\Bans:Types', 'index', $params);
	}
}